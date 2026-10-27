<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\CardInfoDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Card de NFS-e no "Faturamento por modelo": ela mora em `nfse_documents`, não
 * em `documents`, então o widget consulta as duas fontes.
 *
 * Trava também o rótulo do modelo 59: são notas de ENTRADA (compras), fluxo
 * oposto ao da NF-e.
 */
class DashboardNfseCardTest extends TestCase
{
    use DatabaseTransactions;

    private const COD = '9933150327000000000556667772027099900030';

    private function widget(?array $search = null)
    {
        $admin = User::where('email', 'admin@gmail.com')->firstOrFail();
        $this->actingAs($admin);

        $c = Livewire::test(CardInfoDocument::class, ['user' => $admin]);

        return $search === null ? $c : $c->call('eventDocsSearch', $search);
    }

    private function criaNfse(string $cod, float $valor, string $data = '2027-08-10', string $amb = '1'): void
    {
        DB::table('nfse_documents')->insert([
            'padrao' => 'ipm', 'cnpj_prestador' => '09617165000181', 'numero' => '7001',
            'cod_verificacao' => $cod, 'identidade' => 'IPM:' . $cod,
            'situacao' => 'Autorizada', 'valor' => $valor, 'issue_dh' => $data,
            'month_year' => '202708', 'environment_type' => $amb,
        ]);
    }

    public function test_card_nfse_soma_quantidade_e_valor(): void
    {
        $this->criaNfse(self::COD, 250.00);
        $this->criaNfse(self::COD . 'B', 100.50);

        $this->widget(['first_date' => '2027-08-01', 'last_date' => '2027-08-31'])
            ->assertSet('qty_nfse', 2)
            ->assertSet('total_nfse', 350.50);
    }

    public function test_card_nfse_respeita_o_periodo(): void
    {
        $this->criaNfse(self::COD, 250.00, '2027-08-10');

        $this->widget(['first_date' => '2027-09-01', 'last_date' => '2027-09-30'])
            ->assertSet('qty_nfse', 0);
    }

    public function test_card_nfse_respeita_o_filtro_de_ambiente(): void
    {
        $this->criaNfse(self::COD, 250.00, '2027-08-10', '1');
        $this->criaNfse(self::COD . 'H', 999.00, '2027-08-10', '2');

        $this->widget([
            'first_date' => '2027-08-01', 'last_date' => '2027-08-31',
            'environment_types' => ['1'],
        ])->assertSet('qty_nfse', 1)->assertSet('total_nfse', 250.00);
    }

    /** Filtrar por outro tipo (ex.: só NF-e) tem de zerar o card, como os demais. */
    public function test_card_nfse_zera_quando_o_filtro_de_tipo_exclui_a_nfse(): void
    {
        $this->criaNfse(self::COD, 250.00);

        $this->widget([
            'first_date' => '2027-08-01', 'last_date' => '2027-08-31',
            'doc_types' => ['55'],
        ])->assertSet('qty_nfse', 0);
    }

    /**
     * O componente é #[Lazy]: a 1ª renderização é o skeleton. Passar um filtro
     * dispara o render de verdade, o mesmo caminho do dashboard real.
     */
    public function test_rotulos_dos_cards(): void
    {
        $this->widget(['first_date' => '2027-08-01', 'last_date' => '2027-08-31'])
            ->assertSee('NFS-e')
            ->assertSee('Entrada/Compras')
            ->assertDontSee('NF-e Entrada');
    }
}
