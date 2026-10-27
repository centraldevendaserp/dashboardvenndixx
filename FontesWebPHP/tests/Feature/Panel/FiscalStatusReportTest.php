<?php

namespace Tests\Feature\Panel;

use App\Models\Company;
use App\Models\FiscalStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Relatório imprimível da aba "Status SEFAZ". Stateless: os filtros vêm da query
 * string e são revalidados no controller, e a fonte é a MESMA da tela.
 */
class FiscalStatusReportTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    /** Chave: cUF(2) AAMM(4) CNPJ(14) mod(2) serie(3) nNF(9) tpEmis(1) cNF(8) cDV(1). */
    private function novaLinha(string $cnpj, string $nnf, array $extra = [], string $tpEmis = '1', string $model = '65'): FiscalStatus
    {
        $key = '42' . '2607' . $cnpj . $model . '001' . $nnf . $tpEmis . '00000042' . '0';

        return FiscalStatus::create(array_merge([
            'key' => $key, 'model' => (int) $model, 'cnpj_emit' => $cnpj,
            'series' => 1, 'number' => (int) $nnf, 'cstat' => 704,
            'category' => 'rejeitada', 'x_motivo' => 'Rejeicao: teste',
            'dh_recbto' => now(), 'environment_type' => '2', 'source' => 'pro-lot',
        ], $extra));
    }

    private function empresaIsolada(string $cnpj): Company
    {
        $company = new Company(['cnpj_cpf' => $cnpj, 'corporate_name' => 'EMPRESA RELATORIO LTDA']);
        $company->save();

        return $company;
    }

    public function test_relatorio_lista_o_universo_e_nao_as_autorizadas(): void
    {
        $cnpj = '31313131000131';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654400');                                            // rejeitada normal: ENTRA
        $this->novaLinha($cnpj, '987654401', ['cstat' => 100, 'category' => 'autorizada'], '9'); // contingência RESOLVIDA: fora
        $this->novaLinha($cnpj, '987654402', ['cstat' => 100, 'category' => 'autorizada'], '1'); // autorizada normal: fora

        $this->actingAs($this->admin());

        $this->get(route('panel.fiscal_status.report', ['company' => $cnpj]))
            ->assertOk()
            ->assertSee('987654400')
            ->assertDontSee('987654401')
            ->assertDontSee('987654402');
    }

    public function test_relatorio_imprime_contingencia_e_nao_rejeitada(): void
    {
        $cnpj = '32323232000132';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654410', ['cstat' => 217], '9');

        $this->actingAs($this->admin());

        // O 217 numa nota offline é CONTINGÊNCIA, não rejeição: a SEFAZ nunca a
        // recebeu. A linha tem UMA situação.
        $this->get(route('panel.fiscal_status.report', ['company' => $cnpj]))
            ->assertOk()
            ->assertSee('Contingência (217)')
            ->assertDontSee('Rejeitada (217)');
    }

    public function test_relatorio_respeita_os_chips(): void
    {
        $cnpj = '33333333000133';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654420', [], '1');                // rejeitada normal
        $this->novaLinha($cnpj, '987654421', ['cstat' => 217], '9');  // contingência

        $this->actingAs($this->admin());

        $this->get(route('panel.fiscal_status.report', ['company' => $cnpj, 'filters' => ['contingencia']]))
            ->assertOk()
            ->assertDontSee('987654420')
            ->assertSee('987654421');
    }

    public function test_relatorio_ignora_filtro_fora_da_whitelist(): void
    {
        $cnpj = '34343434000134';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654430');

        $this->actingAs($this->admin());

        // 'autorizada' não é chip desta aba: é DESCARTADO (não vira erro nem filtro)
        $this->get(route('panel.fiscal_status.report', ['company' => $cnpj, 'filters' => ['autorizada']]))
            ->assertOk()
            ->assertSee('987654430');
    }

    public function test_relatorio_e_null_safe_no_periodo(): void
    {
        // 217 real não traz data (dh_recbto NULL) — o recorte não pode escondê-la.
        $cnpj = '35353535000135';
        $this->empresaIsolada($cnpj);
        $this->novaLinha($cnpj, '987654440', ['dh_recbto' => null, 'cstat' => 217,
            'x_motivo' => 'Rejeicao: nao consta na base']);

        $this->actingAs($this->admin());

        $this->get(route('panel.fiscal_status.report', [
            'company' => $cnpj, 'first_date' => '2026-01-01', 'last_date' => '2026-12-31',
        ]))->assertOk()->assertSee('987654440');
    }

    public function test_relatorio_nao_fura_o_escopo_por_empresa(): void
    {
        $cnpjAlheio = '36363636000136';
        $this->novaLinha($cnpjAlheio, '987654450');

        $user = new User([
            'name' => 'Escopo Relatorio', 'email' => 'escopo-relatorio@teste.dev',
            'password' => bcrypt('secret123'),
        ]);
        $user->is_admin = 'N';
        $user->save();

        $company = new Company(['cnpj_cpf' => '37373737000137', 'corporate_name' => 'OUTRA RELATORIO LTDA']);
        $company->save();
        $user->companies()->attach($company->id);

        // Linha da empresa vinculada: prova a metade positiva do escopo, senão
        // uma página vazia também passaria no assertDontSee abaixo.
        $this->novaLinha('37373737000137', '987654451');

        $this->actingAs($user);

        // ?company= com CNPJ alheio não pode furar o escopo do usuário
        $this->get(route('panel.fiscal_status.report', ['company' => $cnpjAlheio]))
            ->assertOk()
            ->assertSee('987654451')
            ->assertDontSee('987654450');
    }

    public function test_relatorio_ignora_filters_aninhado_sem_estourar(): void
    {
        // ?filters[0][]=a manda array aninhado em vez de string, o que estoura
        // "Array to string conversion" no array_intersect.
        $this->actingAs($this->admin());

        $this->get('/panel/fiscal-status/report?filters[0][]=a&filters[1][]=b')
            ->assertOk();
    }

    public function test_relatorio_ignora_data_inexistente_sem_estourar(): void
    {
        // 2026-13-99 passa na regex de formato mas não existe como data:
        // estoura no Carbon::parse da view. O filtro tem de ser descartado.
        $this->actingAs($this->admin());

        $this->get(route('panel.fiscal_status.report', ['first_date' => '2026-13-99']))
            ->assertOk();
    }

    public function test_admin_ve_cnpj_sem_empresa_cadastrada(): void
    {
        // Semântica da aba: admin NÃO é escopado (13 das 60 linhas reais são de
        // CNPJ sem Company). O relatório do admin tem de bater com a TELA dele.
        $cnpjSemCadastro = '38383838000138';
        Company::where('cnpj_cpf', $cnpjSemCadastro)->delete();
        $this->novaLinha($cnpjSemCadastro, '987654460');

        $this->actingAs($this->admin());

        $this->get(route('panel.fiscal_status.report', ['search' => '987654460']))
            ->assertOk()
            ->assertSee('987654460');
    }
}
