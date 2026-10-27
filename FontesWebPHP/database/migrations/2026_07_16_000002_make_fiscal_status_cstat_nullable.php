<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * cstat vira NULLABLE: o canal do ERP manda NF-e rejeitada sem cStat, porque
     * a tabela de NF-e do ERP não tem essa coluna. Idempotente.
     */
    public function up(): void
    {
        if (! Schema::hasTable('fiscal_status')) {
            return;
        }

        DB::statement('ALTER TABLE fiscal_status MODIFY cstat SMALLINT UNSIGNED NULL');
    }

    public function down(): void
    {
        //
    }
};
