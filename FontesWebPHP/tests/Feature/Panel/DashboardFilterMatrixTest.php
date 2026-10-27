<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\CardInfo;
use App\Livewire\Panel\Dashboard\CardInfoDocument;
use App\Livewire\Panel\Dashboard\Invoice;
use App\Livewire\Panel\Dashboard\StatusBreakdown;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Varredura filtro a filtro do dashboard: para cada recorte do QuickFilter,
 * dirige os 4 widgets que contam notas e compara com um COUNT direto no banco.
 * Garante, entre outros, que "Produção" nunca soma nota de homologação.
 */
class DashboardFilterMatrixTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    /** Monta os args no MESMO formato que o QuickFilter dispara. */
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

        if (!empty($args['environment_types'])) {
            $q->whereIn('environment_type', $args['environment_types']);
        }
        if (!empty($args['doc_types'])) {
            $q->whereIn('model', $args['doc_types']);
        }
        if (!empty($args['doc_status'])) {
            $q->whereIn('status_xml', $args['doc_status']);
        }
        if (!empty($args['related_companies'])) {
            $q->whereIn('cnpj_cpf', $args['related_companies']);
        }

        return $q->count();
    }

    /** Cenários de recorte (categóricos — sem período/busca). */
    public static function cenarios(): array
    {
        return [
            'Todos os ambientes'        => [[]],
            'Ambiente Produção'         => [['environment_types' => ['1']]],
            'Ambiente Homologação'      => [['environment_types' => ['2']]],
            'Tipo NF-e (55)'            => [['doc_types' => ['55']]],
            'Tipo NFC-e (65)'           => [['doc_types' => ['65']]],
            'Status Cancelado (101)'    => [['doc_status' => ['101']]],
            'Produção + NF-e'           => [['environment_types' => ['1'], 'doc_types' => ['55']]],
            'Homologação + NFC-e'       => [['environment_types' => ['2'], 'doc_types' => ['65']]],
        ];
    }

    /**
     * @dataProvider cenarios
     */
    public function test_todos_os_widgets_batem_com_o_banco(array $over): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $args = $this->args($over);
        $esperado = $this->esperado($args);

        // 1) Tabela de documentos (Invoice)
        $tabela = Livewire::test(Invoice::class, ['user' => $admin])
            ->dispatch('eventDocsSearch', $args)
            ->instance()->getInvoices(false)->count();
        $this->assertSame($esperado, $tabela, "Tabela (Invoice) divergiu do banco.");

        // 2) Resumo (CardInfo) — KPI "Total filtrado"
        $resumo = Livewire::test(CardInfo::class, ['user' => $admin])
            ->dispatch('eventDocsSearch', $args)
            ->get('invoices_count');
        $this->assertSame($esperado, (int) $resumo, "Resumo (CardInfo) divergiu do banco.");

        // 3) Faturamento por modelo (CardInfoDocument) — soma das quantidades
        $card = Livewire::test(CardInfoDocument::class, ['user' => $admin])
            ->dispatch('eventDocsSearch', $args);
        $somaCard = (int) $card->get('qty_nfe') + (int) $card->get('qty_nfce')
            + (int) $card->get('qty_cte') + (int) $card->get('qty_mdfe') + (int) $card->get('qty_cfesat');
        $this->assertSame($esperado, $somaCard, "Faturamento por modelo (CardInfoDocument) divergiu do banco.");

        // 4) Status das notas (StatusBreakdown) — soma das linhas
        $status = Livewire::test(StatusBreakdown::class, ['user' => $admin])
            ->dispatch('eventDocsSearch', $args)
            ->get('rows');
        $somaStatus = array_sum(array_map(fn ($r) => (int) $r['qty'], $status ?? []));
        $this->assertSame($esperado, $somaStatus, "Status das notas (StatusBreakdown) divergiu do banco.");
    }

    /** O ponto nevrálgico: Produção não pode conter NENHUMA nota de homologação. */
    public function test_producao_nunca_vaza_homologacao(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $prod = $this->esperado($this->args(['environment_types' => ['1']]));
        $homo = $this->esperado($this->args(['environment_types' => ['2']]));
        $todos = $this->esperado($this->args());

        $this->assertSame($todos, $prod + $homo, 'Produção + Homologação deve fechar com o total (sem sobreposição).');

        // e o widget confirma: em Produção, o total == só produção (não o total geral)
        $resumoProd = Livewire::test(CardInfo::class, ['user' => $admin])
            ->dispatch('eventDocsSearch', $this->args(['environment_types' => ['1']]))
            ->get('invoices_count');

        $this->assertSame($prod, (int) $resumoProd);
        $this->assertNotSame($todos, (int) $resumoProd, 'Em Produção o Resumo NÃO pode mostrar o total geral.');
    }
}
