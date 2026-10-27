<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\CardInfo;
use App\Livewire\Panel\Dashboard\Disable;
use App\Livewire\Panel\Dashboard\Event;
use App\Livewire\Panel\Dashboard\Invoice;
use App\Livewire\Panel\Dashboard\QuickFilter;
use App\Models\User;
use App\Support\DashboardStatusScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtro de status do dashboard POR DOMÍNIO. Cada fonte tem o seu e eles não se
 * misturam:
 *   documents.status_xml           -> o vocabulário conhecido menos o 102
 *   disable_documents.event_status -> 102 (inutilização é faixa, não nota)
 *   event_documents.event_status   -> 135
 *
 * Cada aba obedece só aos status do seu domínio e IGNORA (em vez de zerar) os
 * que não lhe pertencem.
 */
class DashboardStatusDomainTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    private function args(array $status): array
    {
        return [
            'first_date' => null, 'last_date' => null,
            'doc_number' => null, 'protocol_number' => null,
            'related_companies' => [], 'doc_types' => [],
            'environment_types' => [], 'doc_status' => $status,
            'quick_search' => null,
        ];
    }

    private function notas(array $status): int
    {
        return Livewire::test(Invoice::class, ['user' => $this->admin()])
            ->dispatch('eventDocsSearch', $this->args($status))
            ->instance()->getInvoices(false)->count();
    }

    private function eventos(array $status): int
    {
        return Livewire::test(Event::class, ['user' => $this->admin()])
            ->dispatch('eventDocsSearch', $this->args($status))
            ->instance()->getEvents(false)->count();
    }

    private function inutilizacoes(array $status): int
    {
        return Livewire::test(Disable::class, ['user' => $this->admin()])
            ->dispatch('eventDocsSearch', $this->args($status))
            ->instance()->getDisables(false)->count();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin());
    }

    /** O vocabulário do filtro espelha o da tela "Documentos" (fonte única). */
    public function test_vocabulario_expande_para_os_codigos_da_tela_documentos(): void
    {
        $this->assertSame([100], DashboardStatusScope::expandir(['autorizada']));
        $this->assertSame([101, 135], DashboardStatusScope::expandir(['cancelada']));
        $this->assertSame([110, 205, 301, 302, 303], DashboardStatusScope::expandir(['denegada']));
        $this->assertSame([102], DashboardStatusScope::expandir(['inutilizada']));

        // combinação preserva os dois grupos, sem repetir código
        $this->assertSame([100, 102], DashboardStatusScope::expandir(['autorizada', 'inutilizada']));
    }

    /** Código cStat cru (sessão/relatório salvos antes da mudança) continua valendo. */
    public function test_expandir_aceita_codigo_cru_por_retrocompatibilidade(): void
    {
        $this->assertSame([100], DashboardStatusScope::expandir(['100']));
        $this->assertSame([101, 135], DashboardStatusScope::expandir([101, 135]));
        $this->assertSame([], DashboardStatusScope::expandir([]));

        // grupo desconhecido não vira código nenhum (em vez de quebrar)
        $this->assertSame([], DashboardStatusScope::expandir(['inexistente']));
    }

    /** Interseção por domínio: o que não é do domínio não recorta (devolve vazio). */
    public function test_interseccao_por_dominio(): void
    {
        // "Inutilizada" não é status de NOTA -> não recorta a tabela de notas
        $this->assertSame([], DashboardStatusScope::paraNotas([102]));
        $this->assertSame([102], DashboardStatusScope::paraInutilizacoes([102]));
        $this->assertSame([], DashboardStatusScope::paraEventos([102]));

        // "Autorizada" é só de NOTA
        $this->assertSame([100], DashboardStatusScope::paraNotas([100]));
        $this->assertSame([], DashboardStatusScope::paraInutilizacoes([100]));

        // "Cancelada" (101+135) vale para NOTA e, pelo 135, para EVENTO
        $this->assertSame([101, 135], DashboardStatusScope::paraNotas([101, 135]));
        $this->assertSame([135], DashboardStatusScope::paraEventos([101, 135]));
        $this->assertSame([], DashboardStatusScope::paraInutilizacoes([101, 135]));
    }

    /** "Inutilizada" tem de mostrar as inutilizações de verdade. */
    public function test_inutilizada_filtra_a_aba_de_inutilizacoes(): void
    {
        $esperado = (int) DB::table('disable_documents')
            ->whereIn('cnpj', DB::table('companies')->pluck('cnpj_cpf'))
            ->where('event_status', 102)
            ->count();

        $this->assertGreaterThan(0, $esperado, 'O dump deveria ter inutilizações homologadas.');
        $this->assertSame($esperado, $this->inutilizacoes([102]));
    }

    /** E NÃO pode mais zerar o resto do dashboard. */
    public function test_inutilizada_nao_zera_as_notas(): void
    {
        $semFiltro = $this->notas([]);
        $this->assertGreaterThan(0, $semFiltro);

        // 102 não é status de nota -> a tabela de notas não é recortada por status
        $this->assertSame($semFiltro, $this->notas([102]),
            'Marcar "Inutilizada" não pode zerar a tabela de notas.');

        // O KPI, ao contrário da tabela, reflete o recorte: filtrar
        // "Inutilizada" mostra quantas são. A regra "não pode ZERAR" continua
        // valendo — o número muda de fonte, não some.
        $inutilizacoes = \Illuminate\Support\Facades\DB::table('disable_documents')
            ->whereIn('cnpj', \App\Models\Company::pluck('cnpj_cpf'))
            ->where('event_status', 102)
            ->count();
        $this->assertGreaterThan(0, $inutilizacoes, 'o cenario precisa ter inutilizacao');

        $kpi = (int) Livewire::withoutLazyLoading()->test(CardInfo::class, ['user' => $this->admin()])
            ->dispatch('eventDocsSearch', $this->args([102]))
            ->get('invoices_count');

        $this->assertSame($inutilizacoes, $kpi,
            'O KPI "Total filtrado" mostra a quantidade de INUTILIZAÇÕES do recorte.');
        $this->assertGreaterThan(0, $kpi, 'O KPI nao pode ZERAR — a regra original segue valendo.');
    }

    /** Simétrico: status de nota não pode zerar as abas Evento/Inutilização. */
    public function test_status_de_nota_nao_zera_eventos_e_inutilizacoes(): void
    {
        $eventosSemFiltro = $this->eventos([]);
        $inutSemFiltro = $this->inutilizacoes([]);

        $this->assertGreaterThan(0, $eventosSemFiltro);
        $this->assertGreaterThan(0, $inutSemFiltro);

        // "Autorizada" (100) não existe no domínio de evento/inutilização
        $this->assertSame($eventosSemFiltro, $this->eventos([100]));
        $this->assertSame($inutSemFiltro, $this->inutilizacoes([100]));
    }

    /** "Cancelada" recorta as notas (101+135) e, pelo 135, os eventos. */
    public function test_cancelada_recorta_notas_e_eventos(): void
    {
        $codes = DashboardStatusScope::expandir(['cancelada']);

        $notasEsperado = (int) DB::table('documents')
            ->whereIn('cnpj_cpf', DB::table('companies')->pluck('cnpj_cpf'))
            ->whereIn('status_xml', [101, 135])
            ->count();

        $eventosEsperado = (int) DB::table('event_documents')
            ->whereIn('cnpj', DB::table('companies')->pluck('cnpj_cpf'))
            ->where('event_status', 135)
            ->count();

        $this->assertSame($notasEsperado, $this->notas($codes));
        $this->assertSame($eventosEsperado, $this->eventos($codes));
    }

    /**
     * Cada status abre a aba onde o resultado dele aparece. Sem status, ou
     * misturando grupos, volta para "Documento Fiscal".
     */
    public function test_aba_padrao_por_status(): void
    {
        $this->assertSame('disable', DashboardStatusScope::abaPara(['inutilizada']));
        $this->assertSame('event', DashboardStatusScope::abaPara(['cancelada']));
        $this->assertSame('authorized', DashboardStatusScope::abaPara(['autorizada']));

        // Denegada não tem aba própria: é status de nota -> lista geral
        $this->assertSame('invoice', DashboardStatusScope::abaPara(['denegada']));

        // nada selecionado -> padrão (todos)
        $this->assertSame('invoice', DashboardStatusScope::abaPara([]));

        // mais de um grupo -> nenhuma aba manda sozinha: visão geral
        $this->assertSame('invoice', DashboardStatusScope::abaPara(['autorizada', 'inutilizada']));
    }

    /** O QuickFilter avisa a aba junto com o filtro, num ciclo só. */
    public function test_quick_filter_despacha_a_aba_do_status(): void
    {
        Livewire::test(QuickFilter::class, ['user' => $this->admin()])
            ->set('doc_status', ['inutilizada'])
            ->call('submit')
            ->assertDispatched('pnlSelecionarAba', fn ($e, $p) => ($p['tipo'] ?? null) === 'disable');

        Livewire::test(QuickFilter::class, ['user' => $this->admin()])
            ->set('doc_status', ['cancelada'])
            ->call('submit')
            ->assertDispatched('pnlSelecionarAba', fn ($e, $p) => ($p['tipo'] ?? null) === 'event');

        Livewire::test(QuickFilter::class, ['user' => $this->admin()])
            ->set('doc_status', ['autorizada'])
            ->call('submit')
            ->assertDispatched('pnlSelecionarAba', fn ($e, $p) => ($p['tipo'] ?? null) === 'authorized');
    }

    /**
     * Limpar devolve a aba padrão e não pode rolar a tela: o clique programático
     * aciona o scroll automático das abas.
     */
    public function test_limpar_volta_para_documento_fiscal_sem_rolar(): void
    {
        Livewire::test(QuickFilter::class, ['user' => $this->admin()])
            ->set('doc_status', ['inutilizada'])
            ->call('submit')
            ->call('resetSearch')
            ->assertDispatched('pnlSelecionarAba',
                fn ($e, $p) => ($p['tipo'] ?? null) === 'invoice' && ($p['rolar'] ?? null) === false);
    }

    /** Já o Aplicar rola até a aba de propósito: o conteúdo dela acabou de mudar. */
    public function test_aplicar_rola_ate_a_aba(): void
    {
        Livewire::test(QuickFilter::class, ['user' => $this->admin()])
            ->set('doc_status', ['inutilizada'])
            ->call('submit')
            ->assertDispatched('pnlSelecionarAba',
                fn ($e, $p) => ($p['tipo'] ?? null) === 'disable' && ($p['rolar'] ?? null) === true);
    }

    /** O QuickFilter despacha CÓDIGOS (o relatório e os widgets dependem disso). */
    public function test_quick_filter_despacha_codigos_expandidos(): void
    {
        $quick = Livewire::test(QuickFilter::class, ['user' => $this->admin()])
            ->set('doc_status', ['cancelada'])
            ->call('submit');

        $args = collect($quick->effects['dispatches'] ?? [])
            ->firstWhere('name', 'eventDocsSearch')['params'][0];

        $this->assertSame([101, 135], $args['doc_status'],
            'O filtro tem de despachar os códigos cStat, não a chave do grupo.');

        // e a sessão do relatório recebe o mesmo recorte
        $this->assertSame([101, 135], session('searchArgsToDocReport')['doc_status']);
    }
}
