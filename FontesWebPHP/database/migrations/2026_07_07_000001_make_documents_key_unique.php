<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dedup no banco por (key, cnpj_cpf), não só por key.
     *
     * ⚠️ A chave de acesso não é única sozinha: a MESMA nota aparece como saída
     * (emitente) e como entrada (destinatário), com CNPJs diferentes. Um UNIQUE
     * só em `key` apagaria a saída ao gravar a entrada.
     *
     * Limpa duplicatas pré-existentes antes de criar o índice (senão ele aborta
     * com 1062) e mantém o nome `documents_key_index`. Idempotente.
     */
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        // já é o UNIQUE composto (key, cnpj_cpf)? -> no-op
        if ($this->isCompositeUnique()) {
            return;
        }

        // 1. Deduplica por (key, cnpj_cpf), mantendo o id mais alto (mais recente).
        DB::statement(
            'DELETE d1 FROM documents d1
               INNER JOIN documents d2
               ON d1.`key` = d2.`key` AND d1.cnpj_cpf = d2.cnpj_cpf AND d1.id < d2.id'
        );

        // 2. Substitui o documents_key_index pelo UNIQUE composto (mesmo nome).
        Schema::table('documents', function (Blueprint $table) {
            if ($this->indexExists('documents_key_index')) {
                $table->dropIndex('documents_key_index');
            }
            $table->unique(['key', 'cnpj_cpf'], 'documents_key_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('documents') || ! $this->isCompositeUnique()) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique('documents_key_index');
            $table->index('key', 'documents_key_index');   // volta ao índice não-único só-key
        });
    }

    private function indexExists(string $index): bool
    {
        return count(DB::select(
            "SELECT 1 FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = 'documents'
                AND index_name = ? LIMIT 1",
            [$index]
        )) > 0;
    }

    /** True se documents_key_index já é UNIQUE e cobre 2 colunas (key + cnpj_cpf). */
    private function isCompositeUnique(): bool
    {
        $cols = DB::select(
            "SELECT MAX(non_unique) AS nu, COUNT(*) AS n FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = 'documents'
                AND index_name = 'documents_key_index'"
        );

        return ! empty($cols) && (int) $cols[0]->n === 2 && (int) $cols[0]->nu === 0;
    }
};
