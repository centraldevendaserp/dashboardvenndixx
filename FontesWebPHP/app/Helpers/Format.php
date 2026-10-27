<?php

namespace App\Helpers;

use Carbon\Carbon;

class Format {

    /**
     * Rótulo do período nos relatórios e no dashboard: sem datas, o par, e cada
     * lado isolado. Um lado só não pode virar "X até X" — o intervalo é aberto.
     *
     * ⚠️ Teste por empty(): Carbon::parse('') devolve HOJE em vez de estourar.
     */
    public static function periodHtml($search)
    {
        $first = $search['first_date'] ?? null;
        $last = $search['last_date'] ?? null;

        if (is_null($search) || (empty($first) && empty($last))) {
            return '<small class="text-black">todos os períodos</small>';
        }

        if (empty($last)) {
            return '<small class="text-black">a partir de ' . Carbon::parse($first)->format('d/m/Y') . '</small>';
        }

        if (empty($first)) {
            return '<small class="text-black">até ' . Carbon::parse($last)->format('d/m/Y') . '</small>';
        }

        return '<small class="text-black">' . Carbon::parse($first)->format('d/m/Y')
            . ' até ' . Carbon::parse($last)->format('d/m/Y') . '</small>';
    }
}
