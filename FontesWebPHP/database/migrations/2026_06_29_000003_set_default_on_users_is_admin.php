<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DEFAULT 'N' em `users.is_admin`, so para linhas FUTURAS.
     *
     * Nao faz backfill (admin existente continua admin) e usa SET DEFAULT, que
     * nao toca tipo, tamanho nem nullability — assim nenhum dado atual e
     * rejeitado. SQL nativo, sem depender do doctrine/dbal. Idempotente.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_admin')) {
            return;
        }

        if ($this->currentDefault() === 'N') {
            return; // ja esta como queremos -> no-op
        }

        DB::statement("ALTER TABLE `users` ALTER COLUMN `is_admin` SET DEFAULT 'N'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_admin')) {
            return;
        }

        // Reverte ao estado da migration de create (sem default). So roda se houver default.
        if ($this->currentDefault() !== null) {
            DB::statement('ALTER TABLE `users` ALTER COLUMN `is_admin` DROP DEFAULT');
        }
    }

    private function currentDefault(): ?string
    {
        $row = DB::selectOne(
            'SELECT COLUMN_DEFAULT AS d FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            ['users', 'is_admin']
        );

        return $row ? $row->d : null;
    }
};
