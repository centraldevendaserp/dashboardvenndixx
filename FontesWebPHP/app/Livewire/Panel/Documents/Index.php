<?php

namespace App\Livewire\Panel\Documents;

use App\Models\Company;
use App\Models\DisableDocument;
use App\Models\Document;
use App\Models\EventDocument;
use App\Models\NfseDocument;
use App\Support\DocumentTypeQuery;
use App\Support\DownloadCleanup;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
use ZipArchive;

/**
 * Tela Documentos por tipo — /panel/documents/{type}, os itens "Documentos
 * Fiscais" da sidebar. O {type} resolve a fonte (documents por modelo,
 * event_documents, disable_documents...), os chips de status e as colunas.
 *
 * Escopo por empresa vem do global scope `linked_user` do Company.
 */
class Index extends Component
{
    use WithPagination;

    public $user;

    public string $type;

    /** Status selecionados nos chips (inteiros). */
    public array $statusFilter = [];

    /** CNPJ da empresa selecionada no select (''; = TODAS as empresas do usuário). */
    public string $company_filter = '';

    /** Período. Os inputs são <input type="date"> → valor em Y-m-d. Vazio = todos. */
    public $first_date;
    public $last_date;

    /** Ambiente (tpAmb): '' = todos, '1' = produção, '2' = homologação. */
    public string $ambiente = '';

    /**
     * Config de cada tipo: rótulo, fonte, modelo e GRUPOS de status contextuais
     * (chaves de statusGroups()). Ver o mapa cStat por modelo no CLAUDE.md.
     */
    public static function types(): array
    {
        // Chips na ordem "bom -> ruim". A lista de cada tipo é o que o time
        // quis filtrar, não todo cStat que o modelo aceita — rejeição de
        // CT-e/MDF-e, por exemplo, mora na aba "Status SEFAZ".
        // "Duplicidade" não tem chip: lista dentro de "Rejeitada" e aparece no
        // badge da linha.
        $nfe = ['autorizada', 'cancelada', 'inutilizada', 'rejeitada', 'denegada'];

        // period: 'month' abre no mês corrente, 'all' abre sem recorte. Todas
        // abrem no mês, e o botão "Limpar" devolve este padrão.
        //
        // ⚠️ A ORDEM daqui é a ordem da SIDEBAR (o menu itera este array), a
        // mesma das abas do agente. As "gerais" e os Descartes fecham a lista.
        return [
            'nfce'          => ['label' => 'NFC-e',           'source' => 'documents', 'model' => 65,   'statuses' => $nfe, 'period' => 'month'],
            'nfe'           => ['label' => 'NF-e',            'source' => 'documents', 'model' => 55,   'statuses' => $nfe, 'period' => 'month'],
            // NFS-e: só Autorizada/Cancelada/Rejeitada — nenhuma fonte produz
            // "Processando"/"Substituída", e chip que nunca lista nada é ruído.
            'nfse'          => ['label' => 'NFS-e',           'source' => 'nfse',      'model' => null, 'statuses' => ['nfse_autorizada', 'nfse_cancelada'], 'period' => 'month'], // tabela nfse_documents; status TEXTUAL
            'mdfe'          => ['label' => 'MDF-e',           'source' => 'documents', 'model' => 58,   'statuses' => ['autorizada', 'cancelada', 'encerrado'], 'period' => 'month'], // MDF-e: sem inutilização; tem Encerrado
            'cte'           => ['label' => 'CT-e',            'source' => 'documents', 'model' => [57, 67], 'statuses' => ['autorizada', 'cancelada', 'inutilizada'], 'period' => 'month'],
            'entrada'       => ['label' => 'Entrada/Compras', 'source' => 'documents', 'model' => 59,   'statuses' => ['autorizada', 'cancelada'], 'period' => 'month'],
            // "gerais": transversais a todos os modelos, filtram só por data.
            'cancelamentos' => ['label' => 'Cancelamentos gerais', 'source' => 'events',   'model' => null, 'statuses' => [], 'period' => 'month'],
            'inutilizacoes' => ['label' => 'Inutilizações gerais', 'source' => 'disables', 'model' => null, 'statuses' => [], 'period' => 'month'],
            // Descartes do SISTEMA: venda jogada fora antes de virar documento.
            // Nao e o evento de inutilizacao de FAIXA (aba acima) — o ERP e que
            // chama as duas coisas de "inutilizada". Vem so do banco do ERP.
            'descartes'     => ['label' => 'Descartes do sistema', 'source' => 'discards', 'model' => null, 'statuses' => [], 'period' => 'month'],
        ];
    }

    /**
     * Grupos de status (os chips). Um grupo cobre vários cStat (Cancelada =
     * 101/135) e "rejeitada" é catch-all. Os grupos nfse_* são TEXTUAIS.
     *
     * ⚠️ Fonte única: o dashboard lê os mesmos grupos daqui. Status novo entra
     * neste array. Mapa de cStat por modelo no CLAUDE.md.
     */
    public static function statusGroups(): array
    {
        return [
            'autorizada'       => ['label' => 'Autorizada',               'codes' => [100],                     'color' => 'green'],
            'cancelada'        => ['label' => 'Cancelada',                'codes' => [101, 135],                'color' => 'red'],
            'denegada'         => ['label' => 'Denegada',                 'codes' => [110, 205, 301, 302, 303], 'color' => 'default'],
            'inutilizada'      => ['label' => 'Inutilizada',              'codes' => [102],                     'color' => 'red'],
            'encerrado'        => ['label' => 'Encerrado',               'codes' => [132],                     'color' => 'blue'],
            'em_processamento' => ['label' => 'Em processamento',        'codes' => [103, 104, 105],           'color' => 'default'],
            'rejeitada'        => ['label' => 'Rejeitada',               'codes' => [], 'catchall' => true,    'color' => 'red'],

            // NFS-e (status TEXTUAL): só o que alguma fonte produz. Provedor
            // que mande situação nova entra aqui — e precisa de parser.
            'nfse_autorizada'  => ['label' => 'Autorizada',  'codes' => [], 'text' => true, 'color' => 'green'],
            'nfse_cancelada'   => ['label' => 'Cancelada',   'codes' => [], 'text' => true, 'color' => 'red'],
        ];
    }

    /** Todos os códigos cStat CONHECIDOS (não-rejeição) — base do catch-all "Rejeitada". */
    public static function knownCodes(): array
    {
        $codes = [];
        foreach (static::statusGroups() as $g) {
            $codes = array_merge($codes, $g['codes'] ?? []);
        }

        return array_values(array_unique($codes));
    }

    /** Dado um status_xml, devolve o grupo (label+cor) para exibir o badge. */
    public static function groupForCode($status): ?array
    {
        $code = (int) $status;

        foreach (static::statusGroups() as $g) {
            if (in_array($code, $g['codes'] ?? [], true)) {
                return $g;
            }
        }

        // Fora dos conhecidos e na faixa 2xx-7xx = Rejeitada.
        return $code >= 200 ? static::statusGroups()['rejeitada'] : null;
    }

    public function mount(string $type): void
    {
        if (! array_key_exists($type, static::types())) {
            abort(404);
        }

        $this->type = $type;
        $this->user = auth('web')->user();

        $this->aplicarFiltrosPadrao();
    }

    /**
     * Estado com que a tela abre (não é "tudo vazio": o período padrão vem do
     * tipo). Fonte única do mount() e do botão Limpar.
     */
    protected function filtrosPadrao(): array
    {
        $mesCorrente = ($this->config()['period'] ?? 'all') === 'month';

        return [
            'first_date'     => $mesCorrente ? Carbon::now()->startOfMonth()->format('Y-m-d') : null,
            'last_date'      => $mesCorrente ? Carbon::now()->format('Y-m-d') : null,
            'statusFilter'   => [],
            'company_filter' => '',
            'ambiente'       => '',
        ];
    }

    protected function aplicarFiltrosPadrao(): void
    {
        foreach ($this->filtrosPadrao() as $propriedade => $valor) {
            $this->{$propriedade} = $valor;
        }
    }

    /** Algum filtro fora do padrão? Controla a exibição do botão Limpar. */
    public function temFiltroAtivo(): bool
    {
        $padrao = $this->filtrosPadrao();

        // (string) normaliza null vs '': o input de data devolve string vazia.
        return (string) $this->first_date !== (string) $padrao['first_date']
            || (string) $this->last_date !== (string) $padrao['last_date']
            || $this->statusFilter !== $padrao['statusFilter']
            || $this->company_filter !== $padrao['company_filter']
            || $this->ambiente !== $padrao['ambiente'];
    }

    /** Restaura o estado inicial da aba (não esvazia: volta ao padrão do tipo). */
    public function limparFiltros(): void
    {
        $this->aplicarFiltrosPadrao();
        $this->resetPage(pageName: 'docs');
    }

    public function getQueryString()
    {
        return [];
    }

    public function paginationView()
    {
        return 'components.layouts.pagination';
    }

    protected function config(): array
    {
        return static::types()[$this->type];
    }

    /** Query da tela (lógica compartilhada com o relatório). */
    protected function typeQuery(): DocumentTypeQuery
    {
        return new DocumentTypeQuery(
            type: $this->type,
            statusFilter: $this->statusFilter,
            companyFilter: $this->company_filter,
            firstDate: (string) $this->first_date,
            lastDate: (string) $this->last_date,
            ambiente: $this->ambiente,
        );
    }

    /** Fonte da tabela principal (regra do chip "Inutilizada" na DocumentTypeQuery). */
    public function effectiveSource(): string
    {
        return $this->typeQuery()->effectiveSource();
    }

    /** Inutilizações da tabela SECUNDÁRIA (chips mistos) — ou null. */
    public function extraInutilizacoes()
    {
        return $this->typeQuery()->extraInutilizacoes();
    }

    /** Rejeições SEM NOTA (fiscal_status) da tabela SECUNDÁRIA — ou null. */
    public function extraRejeicoes()
    {
        return $this->typeQuery()->extraRejeicoes();
    }

    protected function baseQuery()
    {
        return $this->typeQuery()->baseQuery();
    }

    protected function scopedCnpjs()
    {
        return $this->typeQuery()->scopedCnpjs();
    }

    protected function periodBounds(): array
    {
        return $this->typeQuery()->periodBounds();
    }

    protected function inutilizacoesQuery(array $models, $companies, ?string $first, ?string $last)
    {
        return $this->typeQuery()->inutilizacoesQuery($models, $companies, $first, $last);
    }

    public function toggleStatus($key): void
    {
        $key = (string) $key;

        if (in_array($key, $this->statusFilter, true)) {
            $this->statusFilter = array_values(array_diff($this->statusFilter, [$key]));
        } else {
            $this->statusFilter[] = $key;
        }

        $this->resetPage(pageName: 'docs');
    }

    /** Cor do badge para a `situacao` TEXTUAL da NFS-e. */
    public static function corSituacaoNfse(?string $situacao): string
    {
        return match ((string) $situacao) {
            'Autorizada', 'Emitida'  => 'green',
            'Cancelada', 'Rejeitada' => 'red',
            // Situação desconhecida: badge neutra, sem chutar rótulo.
            default                  => 'default',
        };
    }

    public function clearStatus(): void
    {
        $this->statusFilter = [];
        $this->resetPage(pageName: 'docs');
    }

    /** Aplica o período digitado (os inputs usam wire:model deferido). */
    public function applyPeriod(): void
    {
        $this->resetPage(pageName: 'docs');
    }

    /** Trocar de ambiente muda o recorte: volta para a primeira página. */
    public function updatedAmbiente(): void
    {
        $this->resetPage(pageName: 'docs');
    }

    /** Troca de empresa no select → volta para a 1ª página. */
    public function updatedCompanyFilter(): void
    {
        $this->resetPage(pageName: 'docs');
    }

    /** Empresas visíveis (para o SELECT de empresa do filtro). Respeita o escopo. */
    protected function visibleCompanies()
    {
        return Company::orderBy('corporate_name')
            ->get(['id', 'corporate_name', 'fantasy_name', 'cnpj_cpf']);
    }

    /**
     * Contadores por status para o cabeçalho — o DETALHAMENTO COMPLETO do tipo,
     * com base em período + empresa. IGNORA o filtro de chip (os chips só recortam
     * a tabela). Zeros são omitidos. Ordem = a de $cfg['statuses'].
     * Devolve [ ['label'=>, 'qty'=>, 'color'=>], ... ].
     */
    /**
     * Card "Valor" — SOMA do recorte que está na tela, sempre o PRIMEIRO card.
     *
     * Acompanha os chips e sai da MESMA query da listagem, então nunca diverge
     * do que está na tela. Fonte sem valor (evento, não nota) mostra R$ 0,00 em
     * vez de sumir.
     */
    protected function valorDoRecorte(): float
    {
        $coluna = match ($this->effectiveSource()) {
            'documents' => 'vNF',
            'nfse'      => 'valor',
            'discards'  => 'value',
            default     => null,   // events / disables / rejeicoes: nao tem valor
        };

        if ($coluna === null) {
            return 0.0;
        }

        // reorder(): o ORDER BY da listagem quebra a agregação sob
        // ONLY_FULL_GROUP_BY.
        return (float) $this->baseQuery()->reorder()->sum($coluna);
    }

    /** O card "Valor", pronto para entrar na frente dos contadores. */
    protected function cardValor(): array
    {
        return [
            'label' => 'Valor',
            'valor' => $this->valorDoRecorte(),
            'color' => 'default',
        ];
    }

    protected function statusCounts(): array
    {
        $cfg = $this->config();
        $companies = $this->scopedCnpjs();
        [$first, $last] = $this->periodBounds();
        $groups = static::statusGroups();

        // Tipos sem chips (Cancelamentos/Inutilizações/Descartes): Valor + Total.
        if (empty($cfg['statuses'])) {
            return [
                $this->cardValor(),
                [
                    'label' => 'Total',
                    'qty'   => (int) $this->baseQuery()->count(),
                    'color' => 'default',
                ],
            ];
        }

        // NFS-e: status TEXTUAL — agrupa por `situacao` e casa com o rótulo do grupo.
        if ($cfg['source'] === 'nfse') {
            $q = NfseDocument::query()->whereIn('cnpj_prestador', $companies);

            if ($first) {
                $q->where('issue_dh', '>=', $first);
            }
            if ($last) {
                $q->where('issue_dh', '<=', $last);
            }

            $byLabel = $q->groupBy('situacao')
                ->selectRaw('situacao, COUNT(*) as qty')
                ->pluck('qty', 'situacao');

            $out = [$this->cardValor()];
            foreach ($cfg['statuses'] as $key) {
                $g = $groups[$key];
                $qty = (int) ($byLabel[$g['label']] ?? 0);
                if ($qty > 0) {
                    $out[] = ['label' => $g['label'], 'qty' => $qty, 'color' => $g['color']];
                }
            }

            return $out;
        }

        // Demais fontes sem tratamento próprio: só o Valor.
        if ($cfg['source'] !== 'documents') {
            return [$this->cardValor()];
        }

        $q = Document::query()
            ->whereIn('cnpj_cpf', $companies)
            ->whereIn('model', (array) $cfg['model']);

        if ($first) {
            $q->where('issue_dh', '>=', $first);
        }
        if ($last) {
            $q->where('issue_dh', '<=', $last);
        }

        $byCode = $q->groupBy('status_xml')
            ->selectRaw('status_xml, COUNT(*) as qty')
            ->pluck('qty', 'status_xml');

        $out = [$this->cardValor()];
        foreach ($cfg['statuses'] as $key) {
            $g = $groups[$key] ?? null;
            if (! $g) {
                continue;
            }

            // Inutilizada NÃO é status_xml — vem de disable_documents (tabela própria).
            if ($key === 'inutilizada') {
                $qty = (int) $this->inutilizacoesQuery(
                    (array) $cfg['model'], $companies, $first, $last
                )->count();
            } else {
                $qty = $this->tallyForGroup($key, $byCode);

                // Chips do ledger somam as SEM NOTA do fiscal_status: recusa
                // não gera nota, então nunca aparece em `documents`.
                if (isset(DocumentTypeQuery::LEDGER_CHIPS[$key])) {
                    $qty += $this->typeQuery()->ledgerCount($key);
                }
            }

            if ($qty > 0) {
                $out[] = ['label' => $g['label'], 'qty' => $qty, 'color' => $g['color']];
            }
        }

        return $out;
    }

    /** Soma as contagens (mapa [status_xml => qty]) que caem no grupo $key. */
    protected function tallyForGroup(string $key, $byCode): int
    {
        $g = static::statusGroups()[$key] ?? null;
        if (! $g) {
            return 0;
        }

        if ($g['catchall'] ?? false) {
            $known = static::knownCodes();
            $sum = 0;
            foreach ($byCode as $code => $n) {
                $c = (int) $code;
                if ($c >= 200 && ! in_array($c, $known, true)) {
                    $sum += (int) $n;
                }
            }

            return $sum;
        }

        $sum = 0;
        foreach ($g['codes'] ?? [] as $c) {
            $sum += (int) ($byCode[$c] ?? 0);
        }

        return $sum;
    }

    public function render()
    {
        $cfg = $this->config();

        return view('livewire.panel.documents.index', [
            'cfg' => $cfg,
            'effectiveSource' => $this->effectiveSource(),   // fonte da tabela principal
            'rows' => $this->baseQuery()->paginate(100, pageName: 'docs'),
            'extraInutilizacoes' => $this->extraInutilizacoes(), // tabela secundária (ou null)
            'extraRejeicoes' => $this->extraRejeicoes(),         // ledger sem nota (ou null)
            'statuses' => $cfg['statuses'],           // chaves de grupo (os chips)
            'statusGroups' => static::statusGroups(),
            'statusCounts' => $this->statusCounts(),  // contadores do cabeçalho
            'companies' => $this->visibleCompanies(), // p/ o select de empresa
        ]);
    }

    /** Download do XML (por fonte, com a policy correspondente). */
    public function downloadDocById($id, $type = 'xml')
    {
        if ($type !== 'xml') {
            return;
        }

        // ⚠️ effectiveSource(), NÃO $cfg['source']: a tabela na tela segue a
        // fonte EFETIVA, e resolver pelo source do config entrega o XML errado.
        $fonte = $this->effectiveSource();

        // Fontes sem XML: recusa explicando, como o downloadXmls já fazia.
        $semXml = [
            'discards'  => 'Descartes do sistema nao tem XML: a nota nao chegou a ser transmitida.',
            'rejeicoes' => 'Recusa da SEFAZ nao gera XML de nota: nao ha o que baixar.',
        ];

        if (isset($semXml[$fonte])) {
            $this->dispatch('eventCuteAlert', type: 'info', message: $semXml[$fonte]);

            return;
        }

        [$document, $gate] = match ($fonte) {
            'documents' => [Document::find($id), 'access-invoice'],
            'events'    => [EventDocument::find($id), 'access-event-document'],
            'disables'  => [DisableDocument::find($id), 'access-disable-document'],
            'nfse'      => [NfseDocument::find($id), 'access-nfse'],
        };

        if (is_null($document)) {
            $this->dispatch('eventCuteToast', msg: 'Não existe o arquivo para download.', code: 404);
            return;
        }

        if (Gate::inspect($gate, $document)->denied()) {
            $this->dispatch('eventCuteToast', msg: 'Não autorizado.', code: 403);
            return;
        }

        $file = storage_path("app{$document->path_xml}");

        if (! File::exists($file)) {
            $this->dispatch('eventCuteToast', msg: 'Não existe o arquivo para download.', code: 404);
            return;
        }

        return response()->download($file, null, ['Content-Type' => 'application/xml']);
    }

    /**
     * Baixa em ZIP os XMLs do que está na tela: a mesma query da tabela, sem
     * paginação. Com a tabela secundária visível, os XMLs dela vão para a
     * subpasta Inutilizadas/. Gate por documento.
     *
     * Teto de DocumentTypeQuery::MAX_ITEMS, porque o zip é montado de forma
     * síncrona. O envio sai pela rota panel.documents.download (streaming), não
     * pelo Livewire, que estouraria a memória.
     */
    public function downloadXmls()
    {
        $source = $this->effectiveSource();
        $extras = $this->extraInutilizacoes() ?? collect();
        $total = (int) $this->baseQuery()->count() + $extras->count();

        if ($total === 0) {
            $this->dispatch('eventCuteToast', msg: 'Nenhum XML disponível para download.', code: 400);
            $this->dispatch('zip-pronto'); // spinner do botão solta com folga
            return;
        }

        if ($total > DocumentTypeQuery::MAX_ITEMS) {
            $this->dispatch(
                'eventCuteToast',
                msg: 'São ' . number_format($total, 0, ',', '.') . ' documentos no filtro — o limite por download é '
                    . number_format(DocumentTypeQuery::MAX_ITEMS, 0, ',', '.') . '. Refine o período.',
                code: 400
            );
            $this->dispatch('zip-pronto'); // spinner do botão solta com folga
            return;
        }

        // Fontes SEM XML (descarte nunca foi transmitido; recusa não devolve
        // XML): sem esta saída, o match de policy estoura UnhandledMatchError.
        $semXml = [
            'discards'  => 'Descartes do sistema nao tem XML: a nota nao chegou a ser transmitida.',
            'rejeicoes' => 'Recusa da SEFAZ nao gera XML de nota: nao ha o que baixar.',
        ];

        if (isset($semXml[$source])) {
            $this->dispatch('eventCuteAlert', type: 'info', message: $semXml[$source]);
            $this->dispatch('zip-pronto');   // solta o spinner do botão

            return null;
        }

        $gate = match ($source) {
            'documents' => 'access-invoice',
            'events'    => 'access-event-document',
            'disables'  => 'access-disable-document',
            'nfse'      => 'access-nfse',
        };

        $zip = new ZipArchive();
        $time = time();
        $name = "{$this->type}-{$this->user->id}-{$time}.zip";
        $path = storage_path('app/downloads');
        $file = "{$path}/{$name}";

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        DownloadCleanup::limpar($path);

        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->dispatch('eventCuteToast', msg: 'Opss, não foi possível compactar o download', code: 500);
            $this->dispatch('zip-pronto'); // spinner do botão solta com folga
            return;
        }

        $added = 0;

        // cursor(): um model por vez, para o lote inteiro caber na memória.
        foreach ($this->baseQuery()->cursor() as $doc) {
            if (Gate::inspect($gate, $doc)->denied()) {
                continue;
            }

            // Fonte documents organiza em CNPJ/Status; as outras vão na raiz.
            $zipPath = $source === 'documents'
                ? "{$doc->cnpj_cpf}/{$this->zipStatusFolder($doc->status_xml)}/{$doc->key}.xml"
                : null;

            $added += $this->addXmlToZip($zip, $doc, $zipPath);
        }

        foreach ($extras as $doc) {
            if (Gate::inspect('access-disable-document', $doc)->denied()) {
                continue;
            }

            $added += $this->addXmlToZip($zip, $doc, 'Inutilizadas/' . basename((string) $doc->path_xml));
        }

        $zip->close();

        if ($added === 0) {
            File::delete($file);
            $this->dispatch('eventCuteToast', msg: 'Nenhum XML disponível para download.', code: 404);
            $this->dispatch('zip-pronto'); // spinner do botão solta com folga
            return;
        }

        $this->dispatch('zip-pronto'); // spinner do botão solta com folga

        return redirect()->route('panel.documents.download', ['file' => $name]);
    }

    /** Remove zips de downloads antigos (>1h) — órfãos de envios interrompidos. */
    /** Subpasta do zip por status — o MESMO switch do download do dashboard. */
    protected function zipStatusFolder($status): string
    {
        return match ((int) $status) {
            100     => 'Autorizadas',
            101     => 'Canceladas',
            110     => 'Denegadas',
            default => 'Outros',
        };
    }

    /** Adiciona o XML do documento ao zip; devolve 1 se entrou, 0 se pulado. */
    protected function addXmlToZip(ZipArchive $zip, $doc, ?string $zipPath): int
    {
        if (blank($doc->path_xml)) {
            return 0;
        }

        $abs = storage_path("app{$doc->path_xml}");

        if (! File::exists($abs)) {
            return 0;
        }

        $zip->addFile($abs, $zipPath ?: basename((string) $doc->path_xml));

        return 1;
    }
}
