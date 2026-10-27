<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A create de `personal_access_tokens` do projeto é cópia de um stub antigo e
     * tem guarda `hasTable`, então a tabela nunca ganhou a coluna `expires_at`
     * que o Sanctum 4 grava em todo createToken() — sem ela, gerar chave do
     * agente falha com "Unknown column 'expires_at'".
     *
     * Idempotente: só adiciona se ainda não existir.
     */
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        if (! Schema::hasColumn('personal_access_tokens', 'expires_at')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->index()->after('last_used_at');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        if (Schema::hasColumn('personal_access_tokens', 'expires_at')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->dropColumn('expires_at');
            });
        }
    }
};
