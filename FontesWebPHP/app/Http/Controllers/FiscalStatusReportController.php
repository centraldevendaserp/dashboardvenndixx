<?php

namespace App\Http\Controllers;

use App\Support\FiscalStatusQuery;
use Illuminate\Http\Request;

/**
 * Relatório imprimível da aba "Status SEFAZ". Stateless: os filtros vêm da
 * query string e são revalidados aqui. Usa a MESMA fonte da tela
 * (FiscalStatusQuery), para as duas não divergirem.
 */
class FiscalStatusReportController extends Controller
{
    public function __invoke(Request $request)
    {
        // A sanitização dos filtros mora no construtor da FiscalStatusQuery.
        $filters = (array) $request->query('filters', []);

        $first = $this->validDate($request->query('first_date'));
        $last = $this->validDate($request->query('last_date'));

        // Ambiente: só '1'/'2' valem; qualquer outra coisa vira "todos".
        $ambiente = $request->query('ambiente', '');
        $ambiente = in_array($ambiente, ['1', '2'], true) ? $ambiente : '';

        $q = new FiscalStatusQuery(
            filters: $filters,
            companyFilter: is_string($company = $request->query('company', '')) ? $company : '',
            firstDate: $first,
            lastDate: $last,
            search: is_string($search = $request->query('search', '')) ? $search : '',
            ambiente: $ambiente,
        );

        $total = (int) $q->rowsQuery()->count();
        $rows = $q->rowsQuery()->limit(FiscalStatusQuery::MAX_ITEMS)->get();

        // Acima do teto, imprime as primeiras MAX_ITEMS e avisa.
        $capNotice = $total > FiscalStatusQuery::MAX_ITEMS
            ? 'Mostrando os primeiros ' . number_format(FiscalStatusQuery::MAX_ITEMS, 0, ',', '.')
                . ' de ' . number_format($total, 0, ',', '.') . ' registros — refine o período para o relatório completo.'
            : null;

        // Sem espelhar as datas: o periodHtml trata cada lado isolado ("a partir
        // de X" / "até Y"), e espelhar imprimiria "X até X".
        $searchArgs = ($first || $last)
            ? ['first_date' => $first, 'last_date' => $last]
            : null;

        return view('report.fiscal-status', [
            'statuses'              => $rows,
            'capNotice'             => $capNotice,
            'searchArgsToDocReport' => $searchArgs,
        ]);
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
}
