<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Documents\Index as DocumentsIndex;
use App\Livewire\Panel\FiscalStatus\Index as FiscalStatusIndex;
use App\Models\Company;
use App\Models\FiscalStatus;
use App\Models\User;
use App\Support\DocumentTypeQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Chip "Rejeitada" da tela Documentos lendo o ledger `fiscal_status`: recusa não
 * gera nota, então as sem-nota entram como fonte principal (chip sozinho) ou
 * tabela secundária (chip combinado), o mesmo padrão das inutilizações.
 */
class DocumentsRejeicoesTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '77665544000133';

    protected function setUp(): void
    {
        parent::setUp();

        // A tela Documentos escopa por empresas CADASTRADAS (mesmo p/ admin) —
        // rejeição de CNPJ sem cadastro só aparece na aba Status SEFAZ.
        $company = new Company(['cnpj_cpf' => self::CNPJ, 'corporate_name' => 'EMPRESA REJEICOES LTDA']);
        $company->save();
    }

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    /** Chave de 44 dígitos coerente com os campos da linha. */
    private function chave(string $nnf, string $model = '65', string $cnpj = self::CNPJ): string
    {
        return '42' . '2607' . $cnpj . $model . '001' . $nnf . '1' . '00000042' . '0';
    }

    /** Linha do ledger fiscal_status (default: rejeição 704 de NFC-e, agora). */
    private function novaLinha(string $nnf, array $extra = []): FiscalStatus
    {
        return FiscalStatus::create(array_merge([
            'key' => $this->chave($nnf), 'model' => 65, 'cnpj_emit' => self::CNPJ,
            'series' => 1, 'number' => (int) $nnf, 'cstat' => 704,
            'category' => 'rejeitada', 'x_motivo' => 'Rejeicao: teste documentos',
            'dh_recbto' => now(), 'environment_type' => '2', 'source' => 'pro-lot',
        ], $extra));
    }

    public function test_chip_rejeitada_sozinho_vira_fonte_principal(): void
    {
        $this->novaLinha('876543201');
        // Linha autorizada do MESMO CNPJ não pode vazar para a fonte de rejeições.
        $this->novaLinha('876543202', ['category' => 'autorizada', 'cstat' => 100]);

        $this->actingAs($this->admin());

        Livewire::test(DocumentsIndex::class, ['type' => 'nfce'])
            ->call('toggleStatus', 'rejeitada')
            ->assertSee('876543201')
            ->assertDontSee('876543202');

        $q = new DocumentTypeQuery(type: 'nfce', statusFilter: ['rejeitada']);
        $this->assertSame('rejeicoes', $q->effectiveSource());
    }

    public function test_chip_combinado_mostra_tabela_secundaria(): void
    {
        $this->novaLinha('876543203');
        $this->actingAs($this->admin());

        Livewire::test(DocumentsIndex::class, ['type' => 'nfce'])
            ->call('toggleStatus', 'autorizada')
            ->call('toggleStatus', 'rejeitada')
            ->assertSee('Rejeições (sem nota no portal)')
            ->assertSee('876543203');

        $q = new DocumentTypeQuery(type: 'nfce', statusFilter: ['autorizada', 'rejeitada']);
        $this->assertSame('documents', $q->effectiveSource());
        $this->assertSame(1, $q->extraRejeicoes()->where('number', 876543201 + 2)->count());
    }

    public function test_dedup_chave_com_nota_no_portal_nao_aparece(): void
    {
        $rej = $this->novaLinha('876543204');

        // A MESMA chave existe como documento importado: a rejeição sai da secundária.
        DB::table('documents')->insert([
            'cnpj_emit' => self::CNPJ, 'cnpj_cpf' => self::CNPJ, 'ie' => '123',
            'model' => 65, 'series' => 1, 'number' => 876543204, 'key' => $rej->key,
            'month_year' => '202607', 'issue_dh' => now()->toDateString(),
            'path_xml' => '/docs/teste-rejeicao.xml', 'protocol' => '342260000000010',
            'environment_type' => '2', 'status_xml' => '100', 'size' => 1000,
            'vNF' => '10.00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin());

        $q = new DocumentTypeQuery(type: 'nfce', statusFilter: ['autorizada', 'rejeitada']);
        $this->assertSame(0, $q->extraRejeicoes()->where('key', $rej->key)->count());
    }

    public function test_escopo_nao_admin_sem_vinculo_nao_ve(): void
    {
        $this->novaLinha('876543205');

        $user = new User([
            'name' => 'Escopo Rejeicoes', 'email' => 'escopo-rejeicoes@teste.dev',
            'password' => bcrypt('secret123'),
        ]);
        $user->is_admin = 'N';
        $user->save();

        $this->actingAs($user);

        Livewire::test(DocumentsIndex::class, ['type' => 'nfce'])
            ->call('toggleStatus', 'rejeitada')
            ->assertDontSee('876543205');
    }

    public function test_contagem_do_chip_soma_as_sem_nota(): void
    {
        // Filtro pela empresa do setUp: isola a contagem do backfill real que
        // pode existir no banco de dev (fiscal_status é populada por testes e2e).
        $this->novaLinha('876543206');

        $this->actingAs($this->admin());

        Livewire::test(DocumentsIndex::class, ['type' => 'nfce'])
            ->set('company_filter', self::CNPJ)
            ->assertSeeHtml('Rejeitada<b>1</b>');
    }

    public function test_cobertura_de_paginas_do_canal_de_status(): void
    {
        $this->actingAs($this->admin());

        // 'entrada' (model 59) segue FORA do canal — rejeição é do emitente.
        $q = new DocumentTypeQuery(type: 'entrada', statusFilter: ['autorizada', 'rejeitada']);
        $this->assertNull($q->extraRejeicoes());
        $solo = new DocumentTypeQuery(type: 'entrada', statusFilter: ['rejeitada']);
        $this->assertNotSame('rejeicoes', $solo->effectiveSource());

        // 2ª etapa: cte ([57,67]) e mdfe (58) AGORA participam.
        foreach (['cte', 'mdfe'] as $type) {
            $solo = new DocumentTypeQuery(type: $type, statusFilter: ['rejeitada']);
            $this->assertSame('rejeicoes', $solo->effectiveSource(), "chip sozinho em {$type}");
        }
    }

    public function test_chip_rejeitada_da_pagina_cte_mostra_sem_nota_model_57(): void
    {
        $cnpj = self::CNPJ; // Company criada no setUp
        $key = '42' . '2607' . $cnpj . '57' . '001' . '987654311' . '1' . '00000042' . '0';
        FiscalStatus::create([
            'key' => $key, 'model' => 57, 'cnpj_emit' => $cnpj,
            'series' => 1, 'number' => 987654311, 'cstat' => 217,
            'category' => 'rejeitada', 'x_motivo' => 'Rejeicao: CT-e nao consta',
            'dh_recbto' => now(), 'environment_type' => '2', 'source' => 'sit',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(DocumentsIndex::class, ['type' => 'cte'])
            ->call('toggleStatus', 'rejeitada')
            ->assertSee('987654311');
    }

    public function test_status_sefaz_pagina_de_20_em_20(): void
    {
        $this->assertSame(20, (int) config('app.pagination_limit'));

        $this->actingAs($this->admin());

        $perPage = Livewire::test(FiscalStatusIndex::class)
            ->viewData('statuses')->perPage();

        $this->assertSame(20, $perPage);
    }

    public function test_rejeicao_sem_data_aparece_mesmo_com_periodo_setado(): void
    {
        // 217 real não traz data em lugar nenhum (dh_recbto NULL) — o recorte de
        // período não pode escondê-la.
        $this->novaLinha('876543212', ['dh_recbto' => null, 'cstat' => 217,
            'x_motivo' => 'Rejeicao: nao consta na base']);

        $this->actingAs($this->admin());

        Livewire::test(DocumentsIndex::class, ['type' => 'nfce'])
            ->set('first_date', now()->startOfMonth()->format('Y-m-d'))
            ->set('last_date', now()->format('Y-m-d'))
            ->call('toggleStatus', 'rejeitada')
            ->assertSee('876543212');
    }
}
