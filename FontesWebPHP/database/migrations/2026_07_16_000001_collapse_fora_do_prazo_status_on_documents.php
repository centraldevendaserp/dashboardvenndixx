<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colapsa os status "fora do prazo" já gravados (150 -> 100, 151 -> 101). A
     * ingestão passou a colapsar, mas só para dados novos.
     *
     * Importa porque 150/151 saíram de knownCodes(): um residual apareceria sob
     * o chip catch-all "Rejeitada", exibindo nota autorizada como rejeitada.
     *
     * Idempotente (UPDATE condicional) e NÃO altera `fiscal_status`, que guarda
     * o cStat REAL da SEFAZ.
     */
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        // status_xml é varchar(10) — comparar como STRING.
        DB::table('documents')->where('status_xml', '150')->update(['status_xml' => '100']);
        DB::table('documents')->where('status_xml', '151')->update(['status_xml' => '101']);
    }

    /**
     * Sem volta: colapsados, os códigos não se distinguem dos originais. O "fora
     * do prazo" continua em `fiscal_status` para quem passou pelo canal de status.
     */
    public function down(): void
    {
        //
    }
};
