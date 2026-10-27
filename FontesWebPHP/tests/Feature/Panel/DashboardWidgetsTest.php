<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\CardInfo;
use App\Livewire\Panel\Dashboard\CardInfoDocument;
use App\Livewire\Panel\Dashboard\Index as DashboardIndex;
use App\Livewire\Panel\Dashboard\InvoicePerMonth;
use App\Livewire\Panel\Dashboard\InvoiceQtyTotal;
use App\Livewire\Panel\Dashboard\RecentDocuments;
use App\Livewire\Panel\Dashboard\StatusBreakdown;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Widgets do dashboard (lazy-load + índices):
 *  1. cada uma renderiza conteúdo real, não só assertOk;
 *  2. sobrevive à re-hidratação, que é o round-trip do lazy-load;
 *  3. o escopo por empresa continua valendo;
 *  4. os índices de desempenho existem no banco.
 */
class DashboardWidgetsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // O período padrão das widgets é o mês corrente, e o dump é um snapshot
        // congelado: sem travar o relógio, os testes quebrariam por calendário.
        $maxIssue = DB::table('documents')->max('issue_dh');

        if ($maxIssue) {
            Carbon::setTestNow(Carbon::parse($maxIssue)->endOfDay());
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    /** Todos os componentes-widget do dashboard que receberam lazy-load. */
    private static function widgets(): array
    {
        return [
            CardInfo::class,
            CardInfoDocument::class,
            InvoicePerMonth::class,
            InvoiceQtyTotal::class,
            StatusBreakdown::class,
            RecentDocuments::class,
        ];
    }

    /* ---------------------------------------------------------------- */
    /* 1. Conteúdo real de cada widget (admin / dump)                    */
    /* ---------------------------------------------------------------- */

    public function test_card_info_conta_empresas_e_notas_para_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(CardInfo::class, ['user' => $admin, 'lazy' => false])
            ->assertOk()
            ->assertSet('companies_count', Company::count())
            ->tap(function ($c) {
                $this->assertGreaterThan(0, $c->get('companies_count'));
                $this->assertGreaterThan(0, $c->get('invoices_count'));
            });
    }

    public function test_status_breakdown_traz_status_para_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $rows = Livewire::test(StatusBreakdown::class, ['user' => $admin, 'lazy' => false])
            ->assertOk()
            ->get('rows');

        $this->assertNotEmpty($rows, 'StatusBreakdown deveria trazer ao menos um status no mês corrente.');
    }

    public function test_recent_documents_lista_documentos_para_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $documents = Livewire::test(RecentDocuments::class, ['user' => $admin, 'lazy' => false])
            ->assertOk()
            ->viewData('documents');

        $this->assertNotEmpty($documents, 'Deveria listar documentos recentes do dump.');
        $this->assertLessThanOrEqual(6, $documents->count(), 'Recentes é limitado a 6.');
    }

    public function test_invoice_per_month_e_qty_total_tem_dados_para_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $perMonth = Livewire::test(InvoicePerMonth::class, ['user' => $admin, 'lazy' => false])
            ->assertOk()->get('invoices');
        $qtyTotal = Livewire::test(InvoiceQtyTotal::class, ['user' => $admin, 'lazy' => false])
            ->assertOk()->get('invoices');

        $this->assertNotEmpty($perMonth, 'Emissões por período (6 meses) deveria ter dados.');
        $this->assertNotEmpty($qtyTotal, 'Distribuição por modelo deveria ter dados.');
    }

    public function test_card_info_document_calcula_totais_para_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // Modelo 65 (NFC-e) domina o dump → qty_nfce > 0 no período.
        Livewire::test(CardInfoDocument::class, ['user' => $admin, 'lazy' => false])
            ->assertOk()
            ->tap(function ($c) {
                $soma = $c->get('qty_nfe') + $c->get('qty_nfce') + $c->get('qty_cte')
                    + $c->get('qty_mdfe') + $c->get('qty_cfesat');
                $this->assertGreaterThan(0, $soma, 'Algum modelo deveria ter quantidade > 0.');
            });
    }

    /* ---------------------------------------------------------------- */
    /* 2. Re-hidratação — proxy do round-trip do lazy-load              */
    /* ---------------------------------------------------------------- */

    public function test_widgets_sobrevivem_a_rehidratacao(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        foreach (self::widgets() as $widget) {
            // $refresh hidrata o componente a partir do snapshot (serializa/
            // deserializa o $user) e re-renderiza — mesmo caminho do lazy-load.
            Livewire::test($widget, ['user' => $admin])
                ->call('$refresh')
                ->assertOk();
        }
    }

    /* ---------------------------------------------------------------- */
    /* 3. Escopo por empresa (global scope linked_user)                 */
    /* ---------------------------------------------------------------- */

    public function test_nao_admin_so_enxerga_a_propria_empresa(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);
        $company = Company::create([
            'corporate_name' => 'Escopo Widget LTDA',
            'cnpj_cpf' => '12345678000199',
        ]);
        $user->companies()->attach($company->id);

        $this->actingAs($user);

        // Para o não-admin, getCompanies() (Company::pluck) deve devolver SÓ a dele.
        Livewire::test(CardInfo::class, ['user' => $user, 'lazy' => false])
            ->assertOk()
            ->assertSet('companies_count', 1);

        // E as demais widgets não podem quebrar para o usuário comum.
        Livewire::test(StatusBreakdown::class, ['user' => $user, 'lazy' => false])->assertOk();
        Livewire::test(RecentDocuments::class, ['user' => $user, 'lazy' => false])->assertOk();
    }

    public function test_admin_enxerga_todas_as_empresas(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(CardInfo::class, ['user' => $admin, 'lazy' => false])
            ->assertSet('companies_count', Company::count());
    }

    /* ---------------------------------------------------------------- */
    /* 4. Lazy ativo + índices presentes                                */
    /* ---------------------------------------------------------------- */

    public function test_dashboard_entrega_esqueletos_lazy_e_nao_o_conteudo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(DashboardIndex::class)
            ->assertOk()
            ->assertSee('pnl-skel')        // placeholders presentes
            ->assertDontSee('apexcharts-canvas'); // gráfico real ainda não montado
    }

    public function test_indices_de_desempenho_existem_em_documents(): void
    {
        $esperados = [
            'documents_cnpj_cpf_index',
            'documents_issue_dh_index',
            'documents_model_index',
            'documents_status_xml_index',
        ];

        // alias explícito: o information_schema do MySQL 8 devolve a coluna como
        // INDEX_NAME (maiúsculo); o alias garante a chave em minúsculo.
        $existentes = collect(DB::select(
            "SELECT DISTINCT index_name AS idx FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = 'documents'"
        ))->pluck('idx')->all();

        foreach ($esperados as $idx) {
            $this->assertContains(
                $idx,
                $existentes,
                "Índice de desempenho ausente em documents: {$idx}. "
                . "Aplique a migration de índices (ver add_hot_indexes_to_document_tables)."
            );
        }
    }
}
