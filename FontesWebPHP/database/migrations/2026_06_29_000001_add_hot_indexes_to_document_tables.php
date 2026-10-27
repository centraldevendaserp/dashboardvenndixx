<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indices nao-unicos nas colunas quentes do painel/relatorio/import: chave,
     * CNPJ/CPF e datas. Sem eles os filtros fazem full table scan.
     *
     * Idempotente: so cria se a tabela/coluna existir e o indice nao existir.
     * Reusa de proposito os MESMOS nomes da 2026_06_26_000001, para nunca
     * duplicar. Nao-unicos, entao nao alteram os INSERT do agente.
     */
    public function up(): void
    {
        // documents: chave (chNFe), cnpj_cpf e colunas de data (issue_dh + month_year)
        $this->addIndexIfMissing('documents', 'cnpj_cpf', 'documents_cnpj_cpf_index');
        $this->addIndexIfMissing('documents', 'key', 'documents_key_index');
        $this->addIndexIfMissing('documents', 'issue_dh', 'documents_issue_dh_index');
        $this->addIndexIfMissing('documents', 'month_year', 'documents_month_year_index');
        $this->addIndexIfMissing('documents', 'status_xml', 'documents_status_xml_index');
        $this->addIndexIfMissing('documents', 'model', 'documents_model_index');

        // event_documents: CNPJ e chave da NFe referenciada + data do evento
        $this->addIndexIfMissing('event_documents', 'cnpj', 'event_documents_cnpj_index');
        $this->addIndexIfMissing('event_documents', 'nfe_key', 'event_documents_nfe_key_index');
        $this->addIndexIfMissing('event_documents', 'event_dh', 'event_documents_event_dh_index');

        // disable_documents (inutilizacoes): CNPJ + data do evento
        $this->addIndexIfMissing('disable_documents', 'cnpj', 'disable_documents_cnpj_index');
        $this->addIndexIfMissing('disable_documents', 'event_dh', 'disable_documents_event_dh_index');
    }

    /**
     * Reverte so o indice exclusivo desta migration. Os demais compartilham nome
     * com a 2026_06_26_000001, que e quem os possui.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('documents', 'documents_month_year_index');
    }

    private function addIndexIfMissing(string $table, string $column, string $index): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        if ($this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($column, $index) {
            $t->index($column, $index);
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($index) {
            $t->dropIndex($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );

        return count($rows) > 0;
    }
};
