<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O dashboard abre em "todos" e agrega a tabela inteira a cada carga; a linha
     * de `documents` é gorda (path_xml LONGTEXT), então o full scan lê muito mais
     * página do que precisa. Estes índices cobrem as agregações:
     *
     *  - (issue_dh, model, vNF): emissões por mês e sparklines;
     *  - (model, vNF): faturamento por modelo.
     *
     * Idempotente e não-único: não muda os INSERT do agente.
     */
    public function up(): void
    {
        $this->addIndexIfMissing('documents', ['issue_dh', 'model', 'vNF'], 'documents_issue_model_vnf_index');
        $this->addIndexIfMissing('documents', ['model', 'vNF'], 'documents_model_vnf_index');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('documents', 'documents_issue_model_vnf_index');
        $this->dropIndexIfExists('documents', 'documents_model_vnf_index');
    }

    private function addIndexIfMissing(string $table, array $columns, string $index): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if ($this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function ($blueprint) use ($columns, $index) {
            $blueprint->index($columns, $index);
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function ($blueprint) use ($index) {
            $blueprint->dropIndex($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return ! empty(DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        ));
    }
};
