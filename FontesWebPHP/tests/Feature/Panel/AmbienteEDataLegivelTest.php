<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Documents\Index;
use App\Livewire\Panel\FiscalStatus\Index as FiscalStatusIndex;
use App\Models\Company;
use App\Models\FiscalStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Quatro regras de leitura:
 *  1. Status SEFAZ abre pré-selecionado em Produção;
 *  2. o período dele abre em 01/01/2000 -> hoje, em vez de vazio;
 *  3. Descartes mostra "—" no Ambiente, porque descarte não tem tpAmb;
 *  4. data sem hora mostra só a data — "00:00" seria lido como erro de fuso.
 */
class AmbienteEDataLegivelTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '09617165000181';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());
    }

    /* ---------------------------- Status SEFAZ ---------------------------- */

    public function test_select_de_ambiente_na_ordem_pedida(): void
    {
        $html = Livewire::test(FiscalStatusIndex::class)->html();

        $todos = strpos($html, 'Todos os ambientes');
        $prod = strpos($html, '>Produção<');
        $homo = strpos($html, '>Homologação<');

        $this->assertNotFalse($todos);
        $this->assertLessThan($prod, $todos, 'Todos vem antes de Produção');
        $this->assertLessThan($homo, $prod, 'Produção vem antes de Homologação');
    }

    public function test_ambiente_ja_vem_em_producao(): void
    {
        Livewire::test(FiscalStatusIndex::class)->assertSet('ambiente', '1');
    }

    public function test_periodo_abre_de_2000_ate_hoje(): void
    {
        Livewire::test(FiscalStatusIndex::class)
            ->assertSet('first_date', '2000-01-01')
            ->assertSet('last_date', \Carbon\Carbon::now()->format('Y-m-d'));
    }

    /* ------------------------- data sem hora falsa ------------------------ */

    public function test_hora_zerada_mostra_so_a_data(): void
    {
        $semHora = $this->rejeicao(970001, '2026-06-15 00:00:00');
        $comHora = $this->rejeicao(970002, '2026-06-15 13:38:00');

        $this->assertSame('15/06/2026', $semHora->dataHoraLegivel());
        $this->assertSame('15/06/2026 13:38', $comHora->dataHoraLegivel());
    }

    public function test_sem_data_nenhuma_mostra_travessao(): void
    {
        $this->assertSame('—', $this->rejeicao(970011, null)->dataHoraLegivel());
    }

    /** E a tela usa o helper — não o format() cru. */
    public function test_tela_do_status_sefaz_nao_mostra_00_00(): void
    {
        $this->rejeicao(970021, '2026-06-15 00:00:00');

        Livewire::test(FiscalStatusIndex::class)
            ->set('ambiente', '')
            ->assertSee('970021')
            ->assertSee('15/06/2026')
            ->assertDontSee('15/06/2026 00:00');
    }

    private function rejeicao(int $numero, ?string $dh): FiscalStatus
    {
        return FiscalStatus::create([
            'key' => '42' . '2606' . self::CNPJ . '65' . '001'
                . str_pad((string) $numero, 9, '0', STR_PAD_LEFT) . '1' . '00000042' . '0',
            'model' => 65, 'cnpj_emit' => self::CNPJ, 'series' => 1, 'number' => $numero,
            'cstat' => null, 'category' => 'rejeitada', 'x_motivo' => 'teste',
            'dh_recbto' => $dh, 'environment_type' => null, 'source' => 'erp',
        ]);
    }

    /* ----------------------------- Descartes ------------------------------ */

    /**
     * Descarte não tem ambiente: o ERP não guarda tpAmb e a nota nunca foi
     * transmitida. Dizer "Produção" faria o valor ser lido como venda real.
     */
    public function test_descarte_mostra_ambiente_desconhecido(): void
    {
        $cnpj = Company::query()->value('cnpj_cpf');
        DB::table('discarded_documents')->insert([
            'cnpj_cpf' => $cnpj, 'model' => 65, 'series' => 1, 'number' => 970031,
            'issue_dh' => '2037-03-01', 'month_year' => '203703', 'value' => 10,
            'situacao_erp' => 'I', 'environment_type' => null, 'identidade' => 'TESTE-AMB-1',
        ]);

        Livewire::test(Index::class, ['type' => 'descartes'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2037-01-01')
            ->set('last_date', '2037-12-31')
            ->assertSee('Ambiente')
            ->assertSee('970031')
            ->assertSee('nunca foi transmitida à SEFAZ');
    }

    /** O import não volta a gravar o palpite de produção. */
    public function test_import_de_descarte_nao_afirma_producao(): void
    {
        $this->postJson('/api/docs/descartes-erp/upload', ['key' => 'Sistema', 'rows' => [[
            'cnpj_cpf' => self::CNPJ, 'model' => 55, 'number' => '970041',
            'series' => '1', 'situacao' => '5', 'emissao' => '2037-09-10', 'valor' => 1, 'key' => '',
        ]]])->assertOk()->assertJson(['gravados' => 1]);

        $this->assertNull(
            DB::table('discarded_documents')->where('number', 970041)->value('environment_type')
        );
    }
}
