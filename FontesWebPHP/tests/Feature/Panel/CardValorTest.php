<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Documents\Index;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Card "Valor" da tela de Documentos: soma o recorte que está na tela e
 * acompanha os chips. Sai da MESMA query da listagem, então não diverge do que
 * o usuário vê.
 */
class CardValorTest extends TestCase
{
    use DatabaseTransactions;

    private string $cnpj;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());
        $this->cnpj = Company::query()->value('cnpj_cpf');
    }

    private function nota(int $numero, int $status, float $valor): void
    {
        DB::table('documents')->insert([
            'key' => str_pad((string) $numero, 44, '3', STR_PAD_LEFT),
            'cnpj_cpf' => $this->cnpj, 'ie' => '123', 'model' => 55, 'series' => 1,
            'number' => $numero, 'month_year' => '209801', 'issue_dh' => '2098-03-10',
            'path_xml' => '/x.xml', 'protocol' => '1', 'environment_type' => '1',
            'status_xml' => $status, 'vNF' => $valor,
        ]);
    }

    private function tela(array $chips = [])
    {
        $c = Livewire::test(Index::class, ['type' => 'nfe'])
            ->set('company_filter', $this->cnpj)
            ->set('first_date', '2098-01-01')
            ->set('last_date', '2098-12-31');

        foreach ($chips as $chip) {
            $c->call('toggleStatus', $chip);
        }

        return $c;
    }

    private function valorDe($componente): float
    {
        $counts = $componente->viewData('statusCounts');
        $this->assertSame('Valor', $counts[0]['label'], 'o card Valor tem de ser o PRIMEIRO');

        return (float) $counts[0]['valor'];
    }

    public function test_sem_chip_soma_tudo_do_periodo(): void
    {
        $this->nota(910001, 100, 100.00);
        $this->nota(910002, 101, 50.00);

        $this->assertEquals(150.00, $this->valorDe($this->tela()));
    }

    public function test_um_chip_soma_so_aquele_status(): void
    {
        $this->nota(910011, 100, 100.00);
        $this->nota(910012, 101, 50.00);

        $this->assertEquals(100.00, $this->valorDe($this->tela(['autorizada'])));
        $this->assertEquals(50.00, $this->valorDe($this->tela(['cancelada'])));
    }

    public function test_dois_chips_somam_os_dois(): void
    {
        $this->nota(910021, 100, 100.00);
        $this->nota(910022, 101, 50.00);
        $this->nota(910023, 110, 33.00);   // denegada, fora do recorte

        $this->assertEquals(150.00, $this->valorDe($this->tela(['autorizada', 'cancelada'])));
    }

    public function test_periodo_sem_nota_mostra_zero(): void
    {
        Livewire::test(Index::class, ['type' => 'nfe'])
            ->set('company_filter', $this->cnpj)
            ->set('first_date', '2097-01-01')
            ->set('last_date', '2097-12-31')
            ->assertViewHas('statusCounts', fn ($c) => $c[0]['label'] === 'Valor'
                && (float) $c[0]['valor'] === 0.0);
    }

    /**
     * Cancelamento e inutilização são eventos, não notas: sem valor a somar,
     * mostra R$ 0,00 em vez de sumir.
     */
    public function test_fonte_sem_valor_mostra_zero_e_nao_some(): void
    {
        foreach (['cancelamentos', 'inutilizacoes'] as $tipo) {
            Livewire::test(Index::class, ['type' => $tipo])
                ->assertViewHas('statusCounts', fn ($c) => $c[0]['label'] === 'Valor'
                    && (float) $c[0]['valor'] === 0.0);
        }
    }

    /** A aba de descartes tem valor próprio (a venda existiu). */
    public function test_descartes_somam_o_valor(): void
    {
        DB::table('discarded_documents')->insert([
            'cnpj_cpf' => $this->cnpj, 'model' => 55, 'series' => 1, 'number' => 910031,
            'issue_dh' => '2098-03-10', 'month_year' => '209803', 'value' => 77.70,
            'situacao_erp' => '5', 'environment_type' => '1', 'identidade' => 'TESTE-DESC-1',
        ]);

        Livewire::test(Index::class, ['type' => 'descartes'])
            ->set('company_filter', $this->cnpj)
            ->set('first_date', '2098-01-01')
            ->set('last_date', '2098-12-31')
            ->assertViewHas('statusCounts', fn ($c) => (float) $c[0]['valor'] === 77.70);
    }

    public function test_o_card_aparece_na_tela_formatado(): void
    {
        $this->nota(910041, 100, 1234.56);

        $this->tela()->assertSee('Valor')->assertSee('1.234,56');
    }
}
