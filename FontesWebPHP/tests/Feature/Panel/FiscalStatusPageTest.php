<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\FiscalStatus\Index as FiscalStatusIndex;
use App\Models\Company;
use App\Models\FiscalStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Aba "Status SEFAZ": render, chips de rejeitada/contingência e escopo por
 * empresa vinculada.
 *
 * Os chips são mutuamente exclusivos, então marcar os dois é a união.
 * Contingência é tpEmis != 1 E cStat 217; rejeitada é recusa real.
 */
class FiscalStatusPageTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    /**
     * Chave: cUF(2) AAMM(4) CNPJ(14) mod(2) serie(3) nNF(9) tpEmis(1) cNF(8) cDV(1).
     * tpEmis '1' = emissão normal; '9' = contingência offline.
     */
    private function novaLinha(string $cnpj, string $nnf, array $extra = [], string $tpEmis = '1', string $model = '65'): FiscalStatus
    {
        $key = '42' . '2607' . $cnpj . $model . '001' . $nnf . $tpEmis . '00000042' . '0';

        return FiscalStatus::create(array_merge([
            'key' => $key, 'model' => (int) $model, 'cnpj_emit' => $cnpj,
            'series' => 1, 'number' => (int) $nnf, 'cstat' => 704,
            'category' => 'rejeitada', 'x_motivo' => 'Rejeicao: teste',
            // Produção, que é o padrão da aba: fixture em homologação sairia do
            // recorte e o teste passaria a medir o filtro de ambiente.
            'dh_recbto' => now(), 'environment_type' => '1', 'source' => 'pro-lot',
        ], $extra));
    }

    /** Empresa cadastrada + company_filter isolam o teste do backfill real (263 linhas). */
    private function empresaIsolada(string $cnpj): Company
    {
        $company = new Company(['cnpj_cpf' => $cnpj, 'corporate_name' => 'EMPRESA ISOLADA LTDA']);
        $company->save();

        return $company;
    }

    public function test_rota_renderiza_para_admin(): void
    {
        $this->actingAs($this->admin());

        $this->get('/panel/fiscal-status')->assertOk()->assertSee('Status SEFAZ');
    }

    public function test_admin_ve_qualquer_cnpj_e_chip_filtra(): void
    {
        // nNF exótico de propósito: o assertDontSee varre o HTML inteiro, e uma
        // sequência curta apareceria por coincidência dentro de alguma chave.
        $cnpj = '11222333000144';
        $this->novaLinha($cnpj, '987654301');
        $this->empresaIsolada($cnpj);
        $this->actingAs($this->admin());

        // company_filter isola das linhas já existentes no banco: sem ele, o
        // teste dependeria da ordenação para cair na 1ª página.
        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->assertSee('987654301')
            ->call('toggleFilter', 'contingencia')
            ->assertDontSee('987654301')
            ->call('toggleFilter', 'contingencia')
            ->call('toggleFilter', 'rejeitada')
            ->assertSee('987654301');
    }

    public function test_usuario_sem_vinculo_nao_ve_e_com_vinculo_ve(): void
    {
        $cnpj = '55666777000188';
        $this->novaLinha($cnpj, '987654302');

        $user = new User([
            'name' => 'Escopo Teste', 'email' => 'escopo-fiscal-status@teste.dev',
            'password' => bcrypt('secret123'),
        ]);
        $user->is_admin = 'N';
        $user->save();

        $this->actingAs($user);
        Livewire::test(FiscalStatusIndex::class)->assertDontSee('987654302');

        $company = new Company(['cnpj_cpf' => $cnpj, 'corporate_name' => 'EMPRESA ESCOPO FISCAL LTDA']);
        $company->save();
        $user->companies()->attach($company->id);

        Livewire::test(FiscalStatusIndex::class)->assertSee('987654302');
    }

    public function test_company_filter_de_cnpj_alheio_nao_fura_o_escopo(): void
    {
        // Linha de um CNPJ que não é do usuário: setar company_filter com ele
        // não pode furar o whereIn do escopo.
        $cnpjAlheio = '99888777000166';
        $this->novaLinha($cnpjAlheio, '987654303');

        $user = new User([
            'name' => 'Escopo Escape Teste', 'email' => 'escopo-escape-fiscal@teste.dev',
            'password' => bcrypt('secret123'),
        ]);
        $user->is_admin = 'N';
        $user->save();

        $company = new Company(['cnpj_cpf' => '11222333000199', 'corporate_name' => 'OUTRA EMPRESA LTDA']);
        $company->save();
        $user->companies()->attach($company->id);

        $this->actingAs($user);
        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpjAlheio)
            ->assertDontSee('987654303');
    }

    public function test_periodo_recorta_por_dh_recbto_ao_aplicar(): void
    {
        // company_filter isola do backfill real (dezenas de linhas espalhadas
        // por junho/2026) — sem isso a asserção de data cairia fora da 1ª página.
        $cnpj = '44555666000177';
        $this->novaLinha($cnpj, '987654304', ['dh_recbto' => '2026-06-15 10:00:00']);
        $this->novaLinha($cnpj, '987654305', ['dh_recbto' => '2026-03-01 10:00:00']);
        $this->empresaIsolada($cnpj);

        $this->actingAs($this->admin());

        // Os inputs são wire:model deferido e quem aplica é o botão: o teste
        // chama applyPeriod explicitamente, senão ele ficaria sem cobertura.
        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->set('first_date', '2026-06-01')
            ->set('last_date', '2026-06-30')
            ->call('applyPeriod')
            ->assertSee('987654304')
            ->assertDontSee('987654305');
    }

    public function test_rejeicao_sem_data_aparece_mesmo_com_periodo_setado(): void
    {
        // O 217 não traz dhRecbto, e o recorte de período não pode escondê-la.
        $cnpj = '19191919000191';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654370', ['cstat' => 217, 'category' => 'rejeitada', 'dh_recbto' => null]);

        $this->actingAs($this->admin());

        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->set('first_date', '2026-01-01')
            ->set('last_date', '2026-12-31')
            ->call('applyPeriod')
            ->assertSee('987654370');
    }

    public function test_apply_period_volta_para_a_primeira_pagina(): void
    {
        $this->actingAs($this->admin());

        // WithPagination (Livewire 3) guarda a página em $paginators['page'], não numa
        // propriedade pública "page" — dot-notation é como o teste alcança o array.
        Livewire::test(FiscalStatusIndex::class)
            ->set('paginators.page', 2)
            ->call('applyPeriod')
            ->assertSet('paginators.page', 1);
    }

    public function test_linha_mdfe_em_contingencia_aparece_com_o_tipo(): void
    {
        $cnpj = '66554433000122';
        $this->empresaIsolada($cnpj);

        // Encerrado (132) é resposta da SEFAZ -> fora do universo, mesmo offline.
        $this->novaLinha($cnpj, '987654310', [
            'cstat' => 132, 'category' => 'encerrado', 'source' => 'sit',
        ], '2', '58');

        // MDF-e emitido em contingência que a SEFAZ não tem: ENTRA.
        $this->novaLinha($cnpj, '987654311', ['cstat' => 217, 'source' => 'sit'], '2', '58');

        $this->actingAs($this->admin());

        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->assertDontSee('987654310')
            ->assertSee('987654311')
            ->assertSee('MDF-e');
    }

    public function test_autorizada_de_emissao_normal_nao_aparece(): void
    {
        $cnpj = '12121212000121';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654320', ['cstat' => 100, 'category' => 'autorizada'], '1');

        $this->actingAs($this->admin());

        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->assertDontSee('987654320');
    }

    public function test_autorizada_em_contingencia_nao_aparece(): void
    {
        $cnpj = '13131313000131';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654321', ['cstat' => 100, 'category' => 'autorizada'], '9');

        $this->actingAs($this->admin());

        // A SEFAZ recebeu e decidiu (cStat != 217): não está em contingência, e
        // o status real dela aparece na tela Documentos.
        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->assertDontSee('987654321');
    }

    public function test_cancelada_em_contingencia_nao_aparece(): void
    {
        $cnpj = '19191919000192';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654322', ['cstat' => 101, 'category' => 'cancelada'], '9');

        $this->actingAs($this->admin());

        // A SEFAZ recebeu e decidiu (cStat != 217): não está em contingência, e
        // o status real dela aparece na tela Documentos.
        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->assertDontSee('987654322');
    }

    public function test_denegada_em_contingencia_nao_aparece(): void
    {
        // Denegada é resposta da SEFAZ: ela RECEBEU a nota. Não é contingência
        // (só o 217 é) e não é rejeitada -> fora do universo.
        $cnpj = '20202020000120';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654323', ['cstat' => 110, 'category' => 'denegada'], '9');

        $this->actingAs($this->admin());

        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->assertDontSee('987654323');
    }

    public function test_chip_contingencia_filtra_pelo_217(): void
    {
        $cnpj = '14141414000141';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654330', [], '1');                      // rejeitada normal
        $this->novaLinha($cnpj, '987654331', ['cstat' => 462], '9');        // rejeição REAL em nota offline
        $this->novaLinha($cnpj, '987654332', ['cstat' => 217], '9');        // contingência: a SEFAZ não tem

        $this->actingAs($this->admin());

        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->call('toggleFilter', 'contingencia')
            ->assertDontSee('987654330')
            ->assertDontSee('987654331')
            ->assertSee('987654332');
    }

    public function test_chips_marcados_juntos_dao_a_uniao(): void
    {
        $cnpj = '15151515000151';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654340', [], '1');                                   // rejeitada normal
        $this->novaLinha($cnpj, '987654341', ['cstat' => 100, 'category' => 'autorizada'], '9'); // fora do universo
        $this->novaLinha($cnpj, '987654342', ['cstat' => 217], '9');                     // contingência

        $this->actingAs($this->admin());

        // Os chips são exclusivos: marcar os dois é a UNIÃO (com AND daria vazio).
        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->call('toggleFilter', 'rejeitada')
            ->call('toggleFilter', 'contingencia')
            ->assertSee('987654340')
            ->assertSee('987654342')
            ->assertDontSee('987654341');
    }

    public function test_contagens_batem_com_o_universo_e_a_soma_fecha(): void
    {
        $cnpj = '16161616000161';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654350', ['cstat' => 100, 'category' => 'autorizada'], '1'); // fora
        $this->novaLinha($cnpj, '987654351', [], '1');                                          // rejeitada
        $this->novaLinha($cnpj, '987654352', ['cstat' => 462], '9');                            // rejeitada (real)
        $this->novaLinha($cnpj, '987654353', ['cstat' => 217], '9');                            // contingência

        $this->actingAs($this->admin());

        $component = Livewire::test(FiscalStatusIndex::class)->set('company_filter', $cnpj);

        $this->assertSame(3, $component->viewData('total'));
        $this->assertSame(2, $component->viewData('counts')['rejeitada']);
        $this->assertSame(1, $component->viewData('counts')['contingencia']);
    }

    /**
     * Invariante da aba: como os chips são exclusivos, a soma deles é o total.
     * Falha se uma nota contar nos dois.
     */
    public function test_soma_dos_chips_bate_com_o_total(): void
    {
        $cnpj = '23232323000123';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654354', [], '1');               // rejeitada normal
        $this->novaLinha($cnpj, '987654355', ['cstat' => 613], '9'); // rejeição real, offline
        $this->novaLinha($cnpj, '987654356', ['cstat' => 217], '9'); // contingência

        $this->actingAs($this->admin());

        $c = Livewire::test(FiscalStatusIndex::class)->set('company_filter', $cnpj);
        $counts = $c->viewData('counts');

        $this->assertSame($c->viewData('total'), $counts['rejeitada'] + $counts['contingencia']);
    }

    public function test_situacao_mostra_contingencia_e_nao_rejeitada(): void
    {
        $cnpj = '18181818000181';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654361', ['cstat' => 217], '9');  // NFC-e offline, a SEFAZ não tem

        $this->actingAs($this->admin());

        // UMA situação: a linha diz Contingência, não Rejeitada.
        $c = Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->assertSee('987654361')
            ->assertSee('Contingência (217)')
            ->assertDontSee('Rejeitada (217)')
            ->assertSee('Contingência: Offline');   // o subtipo, no tooltip

        // UM badge por linha, não dois: sem esta asserção, reintroduzir o badge
        // inline deixaria a suíte verde (as de cima só aferem o rótulo).
        $this->assertSame(1, substr_count($c->html(), 'badge-round'),
            'a linha tem UMA situacao: um badge, nao dois');
    }

    public function test_rejeicao_real_em_nota_offline_mostra_rejeitada(): void
    {
        $cnpj = '17171717000171';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654360', ['cstat' => 462], '9');  // offline, mas a SEFAZ RECEBEU e recusou

        $this->actingAs($this->admin());

        // Foi emitida em contingência, mas já tem desfecho: é rejeitada, e só.
        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->assertSee('987654360')
            ->assertSee('Rejeitada (462)')
            ->assertDontSee('Contingência (462)');
    }

    public function test_coluna_no_portal_saiu_da_tabela(): void
    {
        $cnpj = '21212121000121';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654380');
        $this->actingAs($this->admin());

        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->assertSee('987654380')
            ->assertDontSee('No portal?');
    }

    public function test_filtro_aninhado_na_tela_nao_estoura(): void
    {
        $this->actingAs($this->admin());

        // A fonte única sanitiza os dois braços: a query string do relatório E o
        // wire:model da tela. Valor aninhado é descartado, não estoura.
        Livewire::test(FiscalStatusIndex::class)
            ->set('filters', [['x']])
            ->assertOk();
    }

    public function test_botao_de_relatorio_leva_os_filtros_ativos(): void
    {
        $cnpj = '22222222000122';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654390');
        $this->actingAs($this->admin());

        Livewire::test(FiscalStatusIndex::class)
            ->set('company_filter', $cnpj)
            ->set('first_date', '2026-06-01')
            ->call('applyPeriod')
            ->call('toggleFilter', 'contingencia')
            ->assertSee('Relatório')
            // O 'ambiente' entra mesmo sem o usuário tocar no select: a aba abre em
            // produção, e o relatório TEM de sair com o mesmo recorte da tela.
            ->assertSee(route('panel.fiscal_status.report', [
                'company'    => $cnpj,
                'first_date' => '2026-06-01',
                'ambiente'   => '1',
                // last_date vem do padrão da tela (hoje), não do teste: o link do
                // relatório carrega o recorte INTEIRO, senão ele diverge da tela.
                'last_date'  => \Carbon\Carbon::now()->format('Y-m-d'),
                'filters'    => ['contingencia'],
            ]));
    }
}
