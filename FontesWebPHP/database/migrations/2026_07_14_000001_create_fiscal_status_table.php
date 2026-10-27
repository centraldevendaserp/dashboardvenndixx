<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger de status fiscal por CHAVE: o último status conhecido de cada nota,
     * vindo dos envelopes que o agente envia. Uma linha por chave, vencendo o
     * dhRecbto mais novo. Idempotente.
     */
    public function up(): void
    {
        if (Schema::hasTable('fiscal_status')) {
            return;
        }

        Schema::create('fiscal_status', function (Blueprint $table) {
            $table->id();
            $table->string('key', 44)->unique();
            $table->unsignedSmallInteger('model')->index();      // 55|65 (posições 21-22 da chave)
            $table->string('cnpj_emit', 14)->index();            // posições 7-20 da chave
            $table->unsignedInteger('series');
            $table->unsignedBigInteger('number');
            $table->unsignedSmallInteger('cstat')->index();
            $table->string('category', 20)->index();             // autorizada|cancelada|denegada|inutilizada|rejeitada
            $table->string('x_motivo', 255)->nullable();
            $table->string('n_prot', 30)->nullable();
            $table->dateTime('dh_recbto')->nullable()->index();  // régua de precedência do upsert
            $table->string('environment_type', 1)->nullable();   // tpAmb (1=prod, 2=homolog)
            $table->string('source', 10);                        // pro-lot | sit
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_status');
    }
};
