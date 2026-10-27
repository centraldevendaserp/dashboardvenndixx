<?php

namespace App\Http\Controllers;

use App\Livewire\Panel\Documents\Index as DocumentsIndex;
use App\Support\DocumentTypeQuery;
use App\Support\InutilizacoesOverview;
use Illuminate\Http\Request;

/**
 * Relatório imprimível da tela Documentos por tipo. Stateless: os filtros vêm
 * da query string e são revalidados aqui (a empresa, dentro do
 * DocumentTypeQuery). Renderiza as mesmas views report.* do dashboard.
 *
 * O chip "Rejeitada" sozinho tem braço próprio (report.rejeicoes): rejeição não
 * vira nota, então não tem status_xml nem valor em `documents`.
 */
class DocumentsReportController extends Controller
{
    public function __invoke(Request $request, string $type)
    {
        $types = DocumentsIndex::types();
        abort_unless(array_key_exists($type, $types), 404);

        $cfg = $types[$type];

        $first = $this->validDate($request->query('first_date'));
        $last = $this->validDate($request->query('last_date'));
        $status = array_values(array_intersect(
            // is_string antes: valor aninhado na query string estouraria
            // "Array to string conversion" no array_intersect.
            array_filter((array) $request->query('status', []), 'is_string'),
            $cfg['statuses']
        ));

        // Ambiente: só '1'/'2' valem; qualquer outra coisa vira "todos".
        $ambiente = $request->query('ambiente', '');
        $ambiente = in_array($ambiente, ['1', '2'], true) ? $ambiente : '';

        $q = new DocumentTypeQuery(
            type: $type,
            statusFilter: $status,
            companyFilter: is_string($company = $request->query('company', '')) ? $company : '',
            firstDate: $first,
            lastDate: $last,
            ambiente: $ambiente,
        );

        $total = (int) $q->baseQuery()->count();
        $rows = $q->baseQuery()->limit(DocumentTypeQuery::MAX_ITEMS)->get();

        // Acima do teto, imprime as primeiras MAX_ITEMS e avisa; a Visão geral
        // continua agregando tudo via SQL.
        $capNotice = $total > DocumentTypeQuery::MAX_ITEMS
            ? 'Mostrando os primeiros ' . number_format(DocumentTypeQuery::MAX_ITEMS, 0, ',', '.')
                . ' de ' . number_format($total, 0, ',', '.') . ' registros — refine o período para o relatório completo.'
            : null;

        // Sem espelhar as datas: o periodHtml trata cada lado isolado ("a partir
        // de X" / "até Y"), e espelhar imprimiria "X até X".
        $searchArgs = ($first || $last)
            ? ['first_date' => $first, 'last_date' => $last]
            : null;

        return match ($q->effectiveSource()) {
            'events' => view('report.events', [
                'events' => $rows,
                'capNotice' => $capNotice,
                'searchArgsToDocReport' => $searchArgs,
            ]),
            'disables' => view('report.disables', [
                'disables' => $rows,
                'capNotice' => $capNotice,
                'searchArgsToDocReport' => $searchArgs,
            ]),
            'discards' => view('report.discards', [
                'discards'  => $rows,
                'capNotice' => $capNotice,
                'searchArgsToDocReport' => $searchArgs,
            ]),
            'nfse' => view('report.nfse', [
                'nfses' => $rows,
                'capNotice' => $capNotice,
                'searchArgsToDocReport' => $searchArgs,
            ]),
            'rejeicoes' => view('report.rejeicoes', [
                'rejeicoes' => $rows,
                'capNotice' => $capNotice,
                'searchArgsToDocReport' => $searchArgs,
            ]),
            default => view('report.invoices', [
                'invoices' => $rows,
                'invoicesOverview' => $this->overview($q),
                'capNotice' => $capNotice,
                'searchArgsToDocReport' => $searchArgs,
                'extraDisables' => $q->extraInutilizacoes(),
            ]),
        };
    }

    /**
     * Só Y-m-d (formato do <input type="date">) E data que EXISTA: a regex
     * sozinha deixa passar 2026-13-99, que estoura no Carbon::parse da view.
     */
    private function validDate($value): ?string
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
    }

    /**
     * Visão geral [modelo, status, qty, total], agregada no banco e cobrindo o
     * filtro inteiro mesmo com a lista limitada pelo teto. O reorder() é
     * obrigatório: ORDER BY herdado quebra o GROUP BY sob ONLY_FULL_GROUP_BY.
     */
    private function overview(DocumentTypeQuery $q): array
    {
        $modelLabel = [55 => 'NF-e', 57 => 'CT-e', 58 => 'MDF-e', 59 => 'Entrada', 65 => 'NFC-e', 67 => 'CT-e OS'];

        $notas = $q->baseQuery()
            ->reorder()
            ->selectRaw('model, status_xml, COUNT(*) as qty, SUM(vNF) as total')
            ->groupBy('model', 'status_xml')
            ->get()
            ->map(function ($row) use ($modelLabel) {
                $g = DocumentsIndex::groupForCode($row->status_xml);

                return [
                    'model'      => $modelLabel[(int) $row->model] ?? 'Outros',
                    'status_xml' => $g['label'] ?? "Código {$row->status_xml}",
                    'qty'        => (int) $row->qty,
                    'total'      => (float) $row->total,
                ];
            })
            ->all();

        // Inutilizações no resumo: não estão em `documents`, então sem isto a
        // Visão geral omitiria o que a seção logo abaixo mostra.
        return array_merge($notas, InutilizacoesOverview::linhas($q->inutilizacoesDoRecorte()));
    }
}
