<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DESATIVADA (no-op). Quem cria estes indices, de forma idempotente e com os
     * mesmos nomes, e a 2026_06_29_000001. A versao original nao checava
     * existencia e quebrava com "Duplicate key name" num banco de dump.
     * Mantida so para preservar o historico onde ela ja rodou.
     */
    public function up(): void
    {
        // no-op — ver 2026_06_29_000001_add_hot_indexes_to_document_tables
    }

    public function down(): void
    {
        // no-op
    }
};
