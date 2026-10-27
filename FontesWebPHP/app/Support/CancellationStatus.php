<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Status efetivo da nota considerando o evento de cancelamento.
 *
 * O cancelamento chega como evento separado, e pode chegar antes da nota (ou a
 * nota ser reenviada depois de cancelada). Havendo evento homologado para a
 * chave, o status é 101 — o cStat do protocolo é sempre o da autorização.
 * Fonte única da ingestão de XML e do canal de status.
 */
class CancellationStatus
{
    public static function resolve($chave, $cStat)
    {
        $cancelada = DB::table('event_documents')
            ->where('nfe_key', (string) $chave)
            ->whereIn('event_type', ['110111', '110112'])
            ->whereIn('event_status', [101, 135, 155])
            ->exists();

        return $cancelada ? 101 : $cStat;
    }
}
