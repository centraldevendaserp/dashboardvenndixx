<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colunas que producao ja tinha (criadas fora das migrations) e que a create
     * de `documents` nunca fez. Sem elas o upload de ENTRADA quebra com
     * "Unknown column 'cnpj_emit'".
     *
     * Idempotente e nullable: no-op sobre o dump e sem alterar os INSERT do agente.
     */
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'cnpj_emit')) {
                $table->string('cnpj_emit', 45)->nullable()->after('cnpj_cpf');
            }

            if (! Schema::hasColumn('documents', 'entrada')) {
                // varchar(2) para bater com o schema real de producao.
                $table->string('entrada', 2)->nullable()->default('N');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'cnpj_emit')) {
                $table->dropColumn('cnpj_emit');
            }

            if (Schema::hasColumn('documents', 'entrada')) {
                $table->dropColumn('entrada');
            }
        });
    }
};
