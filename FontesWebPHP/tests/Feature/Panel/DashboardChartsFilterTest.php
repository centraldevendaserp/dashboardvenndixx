<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\InvoicePerMonth;
use App\Livewire\Panel\Dashboard\InvoiceQtyTotal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Os dois gráficos do dashboard escutam eventDocsPerPeriodSearch e obedecem ao
 * mesmo recorte dos demais widgets, tipo de documento incluído. Os widgets que
 * contam notas estão no DashboardFilterMatrixTest.
 */
class DashboardChartsFilterTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    /** Args no MESMO formato que o QuickFilter dispara. */
    private function args(array $over = []): array
    {
        return array_merge([
            'first_date' => null,
            'last_date' => null,
            'doc_number' => null,
            'protocol_number' => null,
            'related_companies' => [],
            'doc_types' => [],
            'environment_types' => [],
            'doc_status' => [],
            'quick_search' => null,
        ], $over);
    }

    /** COUNT autoritativo no banco replicando o WHERE do recorte. */
    private function esperado(array $args): int
    {
        $q = DB::table('documents')
            ->whereIn('cnpj_cpf', DB::table('companies')->pluck('cnpj_cpf'));

        if (!empty($args['doc_types'])) {
            $q->whereIn('model', $args['doc_types']);
        }
        if (!empty($args['environment_types'])) {
            $q->whereIn('environment_type', $args['environment_types']);
        }
        if (!empty($args['doc_status'])) {
            $q->whereIn('status_xml', $args['doc_status']);
        }

        return $q->count();
    }

    /** Filtrar NF-e não pode deixar o donut trazendo a fatia da NFC-e. */
    public function test_distribuicao_por_modelo_so_traz_o_modelo_filtrado(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $invoices = Livewire::test(InvoiceQtyTotal::class, ['user' => $admin])
            ->dispatch('eventDocsPerPeriodSearch', $this->args(['doc_types' => ['55']]))
            ->get('invoices');

        $modelos = array_column($invoices, 'model');

        $this->assertSame(['NF-e'], array_values(array_unique($modelos)),
            'Filtrando NF-e, o donut não pode ter fatia de outro modelo.');
    }

    /** E a contagem do donut tem de bater com o banco em cada recorte. */
    public function test_distribuicao_por_modelo_bate_com_o_banco(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        foreach ([['55'], ['65'], ['55', '65']] as $tipos) {
            $args = $this->args(['doc_types' => $tipos]);

            $invoices = Livewire::test(InvoiceQtyTotal::class, ['user' => $admin])
                ->dispatch('eventDocsPerPeriodSearch', $args)
                ->get('invoices');

            $soma = array_sum(array_map(fn ($i) => (int) $i['qty'], $invoices));

            $this->assertSame($this->esperado($args), $soma,
                'Distribuição por modelo divergiu do banco no recorte ' . implode(',', $tipos));
        }
    }

    /** "Emissões por período" tem o mesmo filtro e o mesmo dever. */
    public function test_emissoes_por_periodo_zera_as_series_dos_modelos_nao_filtrados(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $invoices = Livewire::test(InvoicePerMonth::class, ['user' => $admin])
            ->dispatch('eventDocsPerPeriodSearch', $this->args(['doc_types' => ['55']]))
            ->get('invoices');

        $nfce = array_sum(array_map(fn ($m) => (int) $m['65'], $invoices));
        $nfe = array_sum(array_map(fn ($m) => (int) $m['55'], $invoices));

        $this->assertSame(0, $nfce, 'Filtrando NF-e, a série NFC-e tem de ficar zerada.');
        $this->assertGreaterThan(0, $nfe, 'A série NF-e deveria ter dados (dump real).');
    }

    /**
     * Integração real: opera o QuickFilter como o usuário e alimenta o gráfico
     * com o payload que ele de fato despacha — senão o teste provaria só que o
     * gráfico obedece a um payload montado à mão.
     */
    public function test_quick_filter_move_a_distribuicao_por_modelo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $quick = Livewire::test(\App\Livewire\Panel\Dashboard\QuickFilter::class, ['user' => $admin])
            ->set('doc_types', ['55'])
            ->call('submit');

        $quick->assertDispatched('eventDocsPerPeriodSearch');

        // Extrai os args exatamente como o dashboard os recebe.
        $args = collect($quick->effects['dispatches'] ?? [])
            ->firstWhere('name', 'eventDocsPerPeriodSearch')['params'][0];

        $this->assertSame(['55'], $args['doc_types'], 'O QuickFilter deve enviar o tipo marcado.');

        $invoices = Livewire::test(InvoiceQtyTotal::class, ['user' => $admin])
            ->dispatch('eventDocsPerPeriodSearch', $args)
            ->get('invoices');

        $this->assertSame(['NF-e'], array_values(array_unique(array_column($invoices, 'model'))));
        $this->assertSame($this->esperado($args),
            array_sum(array_map(fn ($i) => (int) $i['qty'], $invoices)));
    }

    /**
     * Buscar uma chave tem de deixar o KPI "Total filtrado" igual à tabela: o
     * Resumo não pode ignorar a busca.
     */
    public function test_resumo_obedece_a_busca_por_chave(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $doc = DB::table('documents')
            ->whereIn('cnpj_cpf', DB::table('companies')->pluck('cnpj_cpf'))
            ->whereNotNull('key')
            ->first();

        $args = $this->args(['quick_search' => $doc->key]);

        $tabela = Livewire::test(\App\Livewire\Panel\Dashboard\Invoice::class, ['user' => $admin])
            ->dispatch('eventDocsSearch', $args)
            ->instance()->getInvoices(false)->count();

        $resumo = Livewire::withoutLazyLoading()
            ->test(\App\Livewire\Panel\Dashboard\CardInfo::class, ['user' => $admin])
            ->dispatch('eventDocsSearch', $args)
            ->get('invoices_count');

        $this->assertSame($tabela, (int) $resumo,
            'Buscando uma chave, o "Total filtrado" tem de bater com a tabela.');
        $this->assertGreaterThan(0, $tabela, 'A chave sondada deveria existir no dump.');
    }

    /** Ordem dos KPIs do Resumo: mês atual à esquerda do total. */
    public function test_resumo_mostra_mes_atual_antes_do_total_filtrado(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // CardInfo é #[Lazy]: sem isto o teste veria só o skeleton do placeholder.
        Livewire::withoutLazyLoading()
            ->test(\App\Livewire\Panel\Dashboard\CardInfo::class, ['user' => $admin])
            ->assertSeeInOrder(['Filtrado no mês atual', 'Total filtrado']);
    }

    /** Combinação tipo + status: o gráfico respeita os dois ao mesmo tempo. */
    public function test_distribuicao_por_modelo_respeita_tipo_e_status_juntos(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $args = $this->args(['doc_types' => ['55'], 'doc_status' => ['100']]);

        $invoices = Livewire::test(InvoiceQtyTotal::class, ['user' => $admin])
            ->dispatch('eventDocsPerPeriodSearch', $args)
            ->get('invoices');

        $soma = array_sum(array_map(fn ($i) => (int) $i['qty'], $invoices));

        $this->assertSame($this->esperado($args), $soma);
    }
}
