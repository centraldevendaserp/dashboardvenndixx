<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\FiscalStatus\Index;
use App\Models\FiscalStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtro de ambiente e selo de homologação na aba "Status SEFAZ": rejeição de
 * homologação convive com a real no mesmo ledger.
 */
class FiscalStatusAmbienteTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '09617165000181';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());

        foreach ([['1', '880001'], ['2', '880002']] as [$amb, $numero]) {
            FiscalStatus::create([
                'key' => '42260509617165000181650010000' . $numero . '132648' . $amb . '00',
                'model' => 65, 'cnpj_emit' => self::CNPJ, 'series' => 1, 'number' => (int) $numero,
                'cstat' => 539, 'category' => 'rejeitada', 'x_motivo' => 'Rejeicao de teste',
                'environment_type' => $amb, 'source' => 'xml', 'dh_recbto' => '2027-06-15 10:00:00',
            ]);
        }
    }

    private function tela(string $ambiente)
    {
        return Livewire::test(Index::class)
            ->set('first_date', '2027-06-01')
            ->set('last_date', '2027-06-30')
            ->set('ambiente', $ambiente);
    }

    public function test_default_mostra_os_dois_ambientes(): void
    {
        $this->tela('')->assertSee('880001')->assertSee('880002');
    }

    public function test_filtro_producao_esconde_homologacao(): void
    {
        $this->tela('1')->assertSee('880001')->assertDontSee('880002');
    }

    public function test_filtro_homologacao_mostra_so_o_teste(): void
    {
        $this->tela('2')->assertSee('880002')->assertDontSee('880001');
    }

    /** Linha sem tpAmb (o canal do ERP não guarda) conta como produção. */
    public function test_ambiente_nulo_conta_como_producao(): void
    {
        FiscalStatus::create([
            'key' => '42260509617165000181650010000880003132648000',
            'model' => 65, 'cnpj_emit' => self::CNPJ, 'series' => 1, 'number' => 880003,
            'cstat' => 539, 'category' => 'rejeitada', 'x_motivo' => 'Sem tpAmb',
            'environment_type' => null, 'source' => 'erp', 'dh_recbto' => '2027-06-15 10:00:00',
        ]);

        $this->tela('1')->assertSee('880003');
        $this->tela('2')->assertDontSee('880003');
    }

    public function test_selo_de_homologacao_aparece(): void
    {
        $this->tela('')->assertSee('Homologação');
    }

    /**
     * ⚠️ O selo não pode usar `badge-round`: a classe é exclusiva da coluna
     * Situação, e há teste travando "uma linha = UM badge de situação".
     */
    public function test_selo_nao_usa_a_classe_do_badge_de_situacao(): void
    {
        $html = $this->tela('2')->html();

        $this->assertStringContainsString('doc-selo-homolog', $html);

        // Um badge-round por linha (a coluna Situação): o selo de ambiente não
        // pode inflar essa conta. Comparação relativa, não número fixo.
        $linhas = substr_count($html, '<tr wire:key="fs-');
        $this->assertGreaterThan(0, $linhas, 'o filtro precisa trazer pelo menos uma linha');
        $this->assertSame($linhas, substr_count($html, 'badge-round'),
            'o selo de ambiente nao pode virar um segundo badge de situacao na linha');
    }

    public function test_relatorio_respeita_o_ambiente(): void
    {
        $base = '/panel/fiscal-status/report?first_date=2027-06-01&last_date=2027-06-30';

        $this->get($base . '&ambiente=1')->assertOk()->assertSee('880001')->assertDontSee('880002');
        $this->get($base . '&ambiente=2')->assertOk()->assertSee('880002')->assertDontSee('880001');
        $this->get($base . '&ambiente=xyz')->assertOk()->assertSee('880001')->assertSee('880002');
    }
}
