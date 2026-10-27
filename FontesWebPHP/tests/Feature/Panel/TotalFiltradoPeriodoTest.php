<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\CardInfo;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Total filtrado" tem de obedecer o PERÍODO do filtro do topo: o rótulo diz
 * "filtrado", então o número tem de bater com a listagem ao lado.
 */
class TotalFiltradoPeriodoTest extends TestCase
{
    use DatabaseTransactions;

    private string $cnpj;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'admin@gmail.com')->firstOrFail();
        $this->actingAs($this->admin);
        $this->cnpj = Company::query()->value('cnpj_cpf');
    }

    private function nota(int $numero, string $data): void
    {
        DB::table('documents')->insert([
            'key' => str_pad((string) $numero, 44, '8', STR_PAD_LEFT),
            'cnpj_cpf' => $this->cnpj, 'ie' => '1', 'model' => 55, 'series' => 1,
            'number' => $numero, 'month_year' => substr(str_replace('-', '', $data), 0, 6),
            'issue_dh' => $data . ' 10:00:00', 'path_xml' => '/x.xml', 'protocol' => '1',
            'environment_type' => '1', 'status_xml' => 100, 'vNF' => 10,
        ]);
    }

    private function widget(array $search)
    {
        return Livewire::test(CardInfo::class, ['user' => $this->admin])
            ->call('eventDocsSearch', $search);
    }

    public function test_total_filtrado_respeita_o_periodo(): void
    {
        $this->nota(980001, '2035-03-10');   // dentro
        $this->nota(980002, '2035-03-20');   // dentro
        $this->nota(980003, '2035-09-10');   // FORA do período

        $this->widget([
            'first_date' => '2035-03-01',
            'last_date'  => '2035-03-31',
        ])->assertSet('invoices_count', 2);
    }

    /** Sem período, continua sendo tudo — data vazia é "sem limite". */
    public function test_sem_periodo_conta_tudo(): void
    {
        $antes = DB::table('documents')->whereIn('cnpj_cpf', Company::pluck('cnpj_cpf'))->count();
        $this->nota(980011, '2035-04-10');

        $this->widget([])->assertSet('invoices_count', $antes + 1);
    }

    /** Só a data inicial: conta do começo do período para a frente. */
    public function test_so_data_inicial(): void
    {
        $this->nota(980021, '2034-01-10');   // antes do corte
        $this->nota(980022, '2036-01-10');   // depois

        $c = $this->widget(['first_date' => '2035-06-01']);
        $comCorte = $c->get('invoices_count');

        $c2 = $this->widget(['first_date' => '2033-01-01']);
        $this->assertGreaterThan($comCorte, $c2->get('invoices_count'), 'a data inicial não recortou');
    }

    /** As inutilizações somadas ao KPI seguem o mesmo período. */
    public function test_inutilizacoes_tambem_respeitam_o_periodo(): void
    {
        DB::table('disable_documents')->insert([
            'environment_type' => '1', 'service' => 'INUTILIZAR', 'uf' => '42', 'year' => '35',
            'cnpj' => $this->cnpj, 'model' => 55, 'series' => 1,
            'number_start' => 980031, 'number_end' => 980031,
            'event_dh' => '2035-03-15 10:00:00', 'event_status' => 102,
            'protocol_number' => '98003199', 'justification' => 'teste',
            'size' => 1, 'path_xml' => '/x.xml',
        ]);

        // Dentro do período: conta.
        $this->widget([
            'doc_status' => [102], 'first_date' => '2035-03-01', 'last_date' => '2035-03-31',
        ])->assertSet('invoices_count', 1);

        // Fora: não conta.
        $this->widget([
            'doc_status' => [102], 'first_date' => '2035-08-01', 'last_date' => '2035-08-31',
        ])->assertSet('invoices_count', 0);
    }
}
