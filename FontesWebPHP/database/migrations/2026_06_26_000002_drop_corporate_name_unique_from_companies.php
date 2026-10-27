<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove o UNIQUE de companies.corporate_name: razao social nao e unica
     * (repete entre CNPJs ou vem vazia), e a chave natural e o cnpj_cpf. Com o
     * unique, o import quebrava e o agente re-tentava para sempre.
     *
     * Idempotente: so remove se o indice existir.
     */
    public function up(): void
    {
        if (! $this->indexExists('companies', 'companies_corporate_name_unique')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique('companies_corporate_name_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies') || $this->indexExists('companies', 'companies_corporate_name_unique')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->unique('corporate_name', 'companies_corporate_name_unique');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return count(DB::select(
            'SELECT 1 FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        )) > 0;
    }
};
