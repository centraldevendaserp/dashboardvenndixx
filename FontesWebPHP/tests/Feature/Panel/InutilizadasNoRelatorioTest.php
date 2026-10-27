<?php

namespace Tests\Feature\Panel;

use App\Http\Controllers\ReportController;
use App\Livewire\Panel\Dashboard\Invoice;
use App\Models\Company;
use App\Models\DisableDocument;
use App\Models\User;
use App\Support\DashboardStatusScope;
use App\Support\DocumentTypeQuery;
use App\Support\InutilizacoesOverview;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

/**
 * Inutilizadas no Relatório e no Baixar XML. Elas são faixa de numeração
 * (disable_documents), não nota, e entram como lista SECUNDÁRIA conforme o
 * filtro de status:
 *
 *   nenhum chip (= todos) ......... traz
 *   'autorizada' .................. não
 *   'autorizada' + 'cancelada' .... não
 *   'autorizada' + 'inutilizada' .. traz
 *   'inutilizada' sozinha ......... não (ela É a lista principal)
 *   'rejeitada' ................... não
 */
class InutilizadasNoRelatorioTest extends TestCase
{
    use DatabaseTransactions; // cria admin de teste — rollback, não suja o dump

    /* ------------------------- a regra (tabela verdade) ------------------------- */

    public static function filtros(): array
    {
        return [
            'nenhum chip = todos'      => [[], true],
            'so autorizada'            => [['autorizada'], false],
            'autorizada + cancelada'   => [['autorizada', 'cancelada'], false],
            'autorizada + inutilizada' => [['autorizada', 'inutilizada'], true],
            'inutilizada sozinha'      => [['inutilizada'], false],
            'denegada'                 => [['denegada'], false],
        ];
    }

    /**
     * @dataProvider filtros
     */
    public function test_regra_de_incluir_inutilizadas(array $grupos, bool $esperado): void
    {
        $this->assertSame(
            $esperado,
            DashboardStatusScope::incluiInutilizacoes($grupos),
            'Filtro [' . implode(',', $grupos) . '] deveria ' . ($esperado ? 'TRAZER' : 'NÃO trazer') . ' inutilizadas.'
        );
    }

    /**
     * "Rejeitada" é catch-all e não tem código: se a regra falasse códigos, ele
     * viraria [] e seria confundido com "todos".
     */
    public function test_rejeitada_nao_e_confundida_com_todos(): void
    {
        $this->assertSame([], DashboardStatusScope::expandir(['rejeitada']), 'pré-condição: rejeitada não tem código');

        $this->assertFalse(
            DashboardStatusScope::incluiInutilizacoes(['rejeitada']),
            'Filtrar por "Rejeitada" não pode trazer inutilizadas (nem ser lido como "todos").'
        );
    }

    /* ----------------- códigos -> grupos (o dashboard guarda cStat) ----------------- */

    public function test_grupos_converte_codigos_de_volta(): void
    {
        $this->assertSame([], DashboardStatusScope::grupos([]));
        $this->assertSame(['autorizada'], DashboardStatusScope::grupos([100]));
        $this->assertSame(['inutilizada'], DashboardStatusScope::grupos([102]));
        $this->assertEqualsCanonicalizing(
            ['autorizada', 'inutilizada'],
            DashboardStatusScope::grupos([100, 102])
        );
        // 101 e 135 são o MESMO grupo (Cancelada) — não pode duplicar.
        $this->assertSame(['cancelada'], DashboardStatusScope::grupos([101, 135]));
    }

    public function test_ida_e_volta_entre_grupos_e_codigos(): void
    {
        foreach ([['autorizada'], ['cancelada'], ['inutilizada'], ['autorizada', 'inutilizada']] as $grupos) {
            $this->assertEqualsCanonicalizing(
                $grupos,
                DashboardStatusScope::grupos(DashboardStatusScope::expandir($grupos)),
                'ida-e-volta deve preservar [' . implode(',', $grupos) . ']'
            );
        }
    }

    /* ------------- tela Documentos: a fonte do relatório E do zip ------------- */

    /** Com "todos" (nenhum chip), o relatório/zip PRECISA trazer as inutilizações. */
    public function test_documentos_com_todos_traz_inutilizacoes(): void
    {
        $q = new DocumentTypeQuery(type: 'nfce', statusFilter: [], companyFilter: '', firstDate: null, lastDate: null);

        $extras = $q->extraInutilizacoes();

        $this->assertNotNull($extras, 'Sem filtro de status (= todos), as inutilizações devem vir.');
        $this->assertGreaterThan(0, $extras->count(), 'O dump tem inutilizações de NFC-e para trazer.');
    }

    public function test_documentos_filtrando_autorizada_nao_traz_inutilizacoes(): void
    {
        $q = new DocumentTypeQuery(type: 'nfce', statusFilter: ['autorizada'], companyFilter: '', firstDate: null, lastDate: null);

        $this->assertNull($q->extraInutilizacoes(), 'Pediu só "Autorizada": não pode vir inutilização junto.');
    }

    public function test_documentos_com_inutilizada_junto_traz(): void
    {
        $q = new DocumentTypeQuery(type: 'nfce', statusFilter: ['autorizada', 'inutilizada'], companyFilter: '', firstDate: null, lastDate: null);

        $extras = $q->extraInutilizacoes();

        $this->assertNotNull($extras);
        $this->assertGreaterThan(0, $extras->count());
    }

    public function test_documentos_com_inutilizada_sozinha_nao_duplica(): void
    {
        $q = new DocumentTypeQuery(type: 'inutilizacoes', statusFilter: [], companyFilter: '', firstDate: null, lastDate: null);

        $this->assertNull(
            $q->extraInutilizacoes(),
            'Na página de Inutilizações elas JÁ são a tabela principal — não podem vir 2x.'
        );
    }

    /* ------------- dashboard: aba "Documento Fiscal" (reports.invoices) ------------- */

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => 'S']);
    }

    /**
     * Args do filtro do topo, no formato que o QuickFilter grava na sessão.
     * O $dia recorta o período: com o teto de MAX_ITEMS, o download só é
     * exercitável num dia de volume pequeno.
     */
    private function argsComStatus(array $codigos, ?string $dia = null): array
    {
        return [
            'first_date' => $dia, 'last_date' => $dia,
            'doc_number' => null, 'protocol_number' => null,
            'related_companies' => [], 'doc_types' => [],
            'environment_types' => [], 'doc_status' => $codigos,
            'quick_search' => null,
        ];
    }

    public function test_dashboard_com_todos_traz_secao_de_inutilizacoes(): void
    {
        $this->actingAs($this->admin());

        $r = $this->withSession([
            'docType' => 'invoice',
            'searchArgsToDocReport' => $this->argsComStatus([]),   // nenhum status = todos
        ])->get('/panel/reports/invoices');

        $r->assertOk();
        $r->assertSee('Inutilizações');
        $r->assertSee('342260000598639'); // protocolo de uma inutilização do dump
    }

    public function test_dashboard_filtrando_autorizada_nao_traz_inutilizacoes(): void
    {
        $this->actingAs($this->admin());

        $r = $this->withSession([
            'docType' => 'invoice',
            'searchArgsToDocReport' => $this->argsComStatus([100]), // só Autorizada
        ])->get('/panel/reports/invoices');

        $r->assertOk();
        $r->assertDontSee('Inutilizações');
    }

    public function test_dashboard_com_inutilizada_junto_traz(): void
    {
        $this->actingAs($this->admin());

        $r = $this->withSession([
            'docType' => 'invoice',
            'searchArgsToDocReport' => $this->argsComStatus([100, 102]),
        ])->get('/panel/reports/invoices');

        $r->assertOk();
        $r->assertSee('Inutilizações');
    }

    /** A aba AUTORIZADAS é um recorte de status_xml=100 — não é "todos". */
    public function test_dashboard_aba_autorizadas_nao_traz_inutilizacoes(): void
    {
        $this->actingAs($this->admin());

        $r = $this->withSession([
            'docType' => 'authorized',
            'searchArgsToDocReport' => $this->argsComStatus([]),
        ])->get('/panel/reports/invoices');

        $r->assertOk();
        $r->assertDontSee('Inutilizações');
    }

    /** Sem filtro nenhum na sessão (usuário nem tocou no topo) = todos. */
    public function test_dashboard_sem_sessao_de_filtro_traz(): void
    {
        $this->actingAs($this->admin());

        $r = $this->withSession(['docType' => 'invoice'])->get('/panel/reports/invoices');

        $r->assertOk();
        $r->assertSee('Inutilizações');
    }

    /* ------------------- dashboard: Baixar XML (zip) da aba Documento Fiscal ------------------- */

    /**
     * Nomes dos arquivos dentro do zip. O filtro chega ao componente por EVENTO,
     * não pela sessão — a sessão é o caminho do relatório.
     */
    private function zipDoDashboard(array $args, string $docType = 'invoice'): array
    {
        Livewire::test(Invoice::class, ['user' => auth()->user(), 'doc_type' => $docType])
            ->call('eventDocsSearch', $args)
            ->call('eventDownloadCompressedDoc');

        $zips = glob(storage_path('app/downloads/invoice-*.zip'));
        $this->assertNotEmpty($zips, 'O download deveria ter gerado um zip.');

        $za = new ZipArchive();
        $za->open(end($zips));
        $nomes = [];
        for ($i = 0; $i < $za->numFiles; $i++) {
            $nomes[] = $za->getNameIndex($i);
        }
        $za->close();

        foreach ($zips as $z) {
            @unlink($z);
        }

        return $nomes;
    }

    public function test_zip_do_dashboard_com_todos_inclui_pasta_inutilizadas(): void
    {
        $this->actingAs($this->admin());

        $nomes = $this->zipDoDashboard($this->argsComStatus([], '2026-05-21'));

        $inut = array_filter($nomes, fn ($n) => str_starts_with($n, 'Inutilizadas/'));

        $this->assertNotEmpty(
            $inut,
            'Com "todos", o zip da aba Documento Fiscal deve trazer a pasta Inutilizadas/. Veio: '
                . implode(', ', array_slice($nomes, 0, 5))
        );
    }

    public function test_zip_do_dashboard_filtrando_autorizada_nao_inclui_inutilizadas(): void
    {
        $this->actingAs($this->admin());

        $nomes = $this->zipDoDashboard($this->argsComStatus([100], '2026-05-21'));

        $inut = array_filter($nomes, fn ($n) => str_starts_with($n, 'Inutilizadas/'));

        $this->assertEmpty($inut, 'Pediu só "Autorizada": o zip não pode trazer inutilizadas.');
        $this->assertNotEmpty($nomes, 'mas as notas do dia devem continuar vindo');
    }

    /* ------------------------- Visão geral (o agregado do topo) ------------------------- */

    /** Acha a linha "Inutilizada" na Visão geral renderizada do relatório. */
    private function linhaInutilizadaDoOverview(string $html): ?array
    {
        // <td>NFC-e</td><td>Inutilizada</td><td>5</td><td>—</td>
        if (! preg_match(
            '#<td>([^<]*)</td>\s*<td>\s*Inutilizada\s*</td>\s*<td>\s*(\d+)\s*</td>\s*<td>\s*([^<]*?)\s*</td>#s',
            $html,
            $m
        )) {
            return null;
        }

        return ['model' => trim($m[1]), 'qty' => (int) $m[2], 'total' => trim($m[3])];
    }

    public function test_visao_geral_do_dashboard_tem_linha_de_inutilizadas(): void
    {
        $this->actingAs($this->admin());

        $r = $this->withSession([
            'docType' => 'invoice',
            'searchArgsToDocReport' => $this->argsComStatus([]),
        ])->get('/panel/reports/invoices');

        $r->assertOk();

        $linha = $this->linhaInutilizadaDoOverview($r->getContent());

        $this->assertNotNull($linha, 'A Visão geral precisa de uma linha "Inutilizada".');
        $this->assertSame('NFC-e', $linha['model']);
        $this->assertSame(5, $linha['qty'], 'São 5 números inutilizados no dump (faixas de 1).');
        $this->assertStringNotContainsString(
            'R$',
            $linha['total'],
            'Inutilização não tem valor — "R$ 0,00" mentiria (parece nota de valor zero).'
        );
    }

    public function test_visao_geral_dos_documentos_tem_linha_de_inutilizadas(): void
    {
        $this->actingAs($this->admin());

        $r = $this->get('/panel/documents/nfce/report?first_date=2026-05-01&last_date=2026-06-30');

        $r->assertOk();

        $linha = $this->linhaInutilizadaDoOverview($r->getContent());

        $this->assertNotNull($linha, 'A Visão geral do relatório por tipo também precisa da linha.');
        $this->assertSame('NFC-e', $linha['model']);
        $this->assertGreaterThan(0, $linha['qty']);
    }

    public function test_visao_geral_nao_traz_inutilizadas_quando_filtra_autorizada(): void
    {
        $this->actingAs($this->admin());

        $r = $this->withSession([
            'docType' => 'invoice',
            'searchArgsToDocReport' => $this->argsComStatus([100]),
        ])->get('/panel/reports/invoices');

        $r->assertOk();
        $this->assertNull(
            $this->linhaInutilizadaDoOverview($r->getContent()),
            'Mesma regra do resto: pediu "Autorizada", a Visão geral não inventa inutilizadas.'
        );
    }

    /**
     * Quantidade = NÚMEROS inutilizados, não registros: a faixa 100->200 é uma
     * linha e 101 números, e as outras linhas do resumo contam 1 por nota.
     */
    public function test_visao_geral_conta_a_faixa_inteira_nao_o_registro(): void
    {
        $cnpj = Company::query()->value('cnpj_cpf');

        // `event_dh` é TIMESTAMP (estoura em 2038-01-19), então nada de 2099
        // como no resto da suíte — 2037 é o "futuro seguro" desta tabela.
        DisableDocument::create([
            'environment_type' => '1', 'service' => 'INUTILIZAR', 'uf' => '42',
            'year' => '37', 'cnpj' => $cnpj, 'model' => 55, 'series' => 9,
            'number_start' => 100, 'number_end' => 200,   // 101 números
            'event_dh' => '2037-03-10 00:00:00', 'event_status' => 102,
            'protocol_number' => 'PROT-FAIXA-TESTE', 'justification' => 'teste',
            'size' => 10, 'path_xml' => '/tmp/faixa.xml',
        ]);

        $this->actingAs($this->admin());

        $r = $this->get('/panel/documents/nfe/report?first_date=2037-03-01&last_date=2037-03-31');

        $r->assertOk();
        $linha = $this->linhaInutilizadaDoOverview($r->getContent());

        $this->assertNotNull($linha);
        $this->assertSame(
            101,
            $linha['qty'],
            'A faixa 100→200 são 101 números inutilizados, não 1 registro.'
        );
    }

    /* ------------- pedido recusado NAO significa numero livre ------------- */

    /**
     * `disable_documents` guarda também pedidos recusados, e recusa não quer
     * dizer "número disponível": o cStat 563 é "já existe pedido com a mesma
     * faixa", ou seja, ela JÁ está inutilizada.
     *
     * ⚠️ Filtrar por event_status=102 esconde essas linhas e faz o portal
     * mostrar como livre um número que não é. Este teste trava isso.
     */
    public function test_recusa_por_faixa_ja_inutilizada_continua_listada(): void
    {
        $naoHomologadas = DisableDocument::where('event_status', '!=', 102)->count();
        $this->assertGreaterThan(0, $naoHomologadas, 'pré-condição: o dump tem um cStat 563');

        $q = new DocumentTypeQuery(type: 'nfce', statusFilter: [], companyFilter: '', firstDate: null, lastDate: null);

        $this->assertSame(
            DisableDocument::where('model', 65)->count(),
            $q->extraInutilizacoes()->count(),
            'Recusa por "já existe pedido" (563) não pode sumir: aquele número ESTÁ inutilizado.'
        );
    }

    /* ------------------- faixas malformadas (o XML manda o que quiser) ------------------- */

    private function criaInutilizacao(array $extra): void
    {
        DisableDocument::create(array_merge([
            'environment_type' => '1', 'service' => 'INUTILIZAR', 'uf' => '42',
            'year' => '37', 'cnpj' => Company::query()->value('cnpj_cpf'),
            'model' => 55, 'series' => 9, 'event_dh' => '2037-04-10 00:00:00',
            'event_status' => 102, 'protocol_number' => 'PROT-MALF', 'justification' => 'teste',
            'size' => 10, 'path_xml' => '/tmp/x.xml',
        ], $extra));
    }

    /**
     * A faixa vem crua do XML em BIGINT UNSIGNED: invertida, a subtração estoura
     * e derruba o relatório inteiro com 500.
     */
    public function test_faixa_invertida_nao_derruba_o_relatorio(): void
    {
        $this->criaInutilizacao(['number_start' => 10, 'number_end' => 5]);

        $this->actingAs($this->admin());

        $r = $this->get('/panel/documents/nfe/report?first_date=2037-04-01&last_date=2037-04-30');

        $r->assertOk(); // antes: 500 (SQLSTATE 22003 / erro 1690)

        $linha = $this->linhaInutilizadaDoOverview($r->getContent());
        $this->assertNotNull($linha);
        $this->assertSame(0, $linha['qty'], 'Faixa invertida não pode subtrair da contagem.');
    }

    /** `nNFFin` ausente no XML grava NULL — a linha sumia do SUM mas ficava na lista. */
    public function test_number_end_nulo_conta_como_faixa_de_um(): void
    {
        $this->criaInutilizacao(['number_start' => 700, 'number_end' => null]);

        $this->actingAs($this->admin());

        $r = $this->get('/panel/documents/nfe/report?first_date=2037-04-01&last_date=2037-04-30');

        $r->assertOk();

        $linha = $this->linhaInutilizadaDoOverview($r->getContent());
        $this->assertNotNull($linha);
        $this->assertSame(1, $linha['qty'], 'Sem número final, a faixa é de 1 número — não zero.');
    }

    /* ------------------- paridade PDF x ZIP (mesmo filtro, mesma tela) ------------------- */

    /**
     * Relatório e "Baixar XML" da mesma tela, sob o mesmo filtro, têm de
     * enxergar as MESMAS inutilizações.
     */
    public static function filtrosQueOZipIgnorava(): array
    {
        return [
            'ambiente'        => [['environment_types' => ['1']]],   // dump é todo homologação
            'numero'          => [['doc_number' => '9999999']],
            'protocolo'       => [['protocol_number' => 'NAO-EXISTE']],
            'busca rapida'    => [['quick_search' => '342260000598639']],
            'status do event' => [['doc_status' => [100]]],
        ];
    }

    /**
     * @dataProvider filtrosQueOZipIgnorava
     */
    public function test_zip_e_pdf_enxergam_as_mesmas_inutilizacoes(array $extra): void
    {
        $args = array_merge($this->argsComStatus([]), $extra);

        $this->actingAs($this->admin());

        // PDF (ReportController lê da sessão)
        session(['docType' => 'invoice', 'searchArgsToDocReport' => $args]);
        $rc = new \ReflectionMethod(ReportController::class, 'extraDisables');
        $rc->setAccessible(true);
        $doPdf = $rc->invoke(app(ReportController::class));
        $idsPdf = $doPdf ? $doPdf->pluck('id')->sort()->values()->all() : [];

        // ZIP (o componente lê de $this->search)
        $comp = Livewire::test(Invoice::class, ['user' => auth()->user()])->call('eventDocsSearch', $args);
        $ri = new \ReflectionMethod(Invoice::class, 'extraDisables');
        $ri->setAccessible(true);
        $doZip = $ri->invoke($comp->instance());
        $idsZip = collect($doZip)->pluck('id')->sort()->values()->all();

        $this->assertSame(
            $idsPdf,
            $idsZip,
            'Relatório e Baixar XML divergiram para o mesmo filtro: PDF=[' . implode(',', $idsPdf)
                . '] ZIP=[' . implode(',', $idsZip) . ']'
        );
    }

    /* --------------------------------- paridade --------------------------------- */

    /** As duas telas respondem igual ao mesmo filtro: reescrever a regra num lado quebra aqui. */
    public function test_paridade_entre_as_duas_telas(): void
    {
        $casos = [
            [[], []],                                  // todos
            [['autorizada'], [100]],
            [['autorizada', 'inutilizada'], [100, 102]],
            [['inutilizada'], [102]],
        ];

        foreach ($casos as [$grupos, $codigos]) {
            $this->assertSame(
                DashboardStatusScope::incluiInutilizacoes($grupos),
                DashboardStatusScope::incluiInutilizacoes(DashboardStatusScope::grupos($codigos)),
                'Telas divergiram para [' . implode(',', $grupos) . ']: a tela Documentos fala grupos, o dashboard fala cStat.'
            );
        }
    }
}
