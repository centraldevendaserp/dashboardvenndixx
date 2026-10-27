<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas descartadas no sistema de vendas: a venda existiu, ganhou número e foi
 * jogada fora antes de virar documento fiscal. Só o ERP sabe delas.
 *
 * Tabela própria de propósito: não é `documents` (estas nunca foram à SEFAZ) nem
 * `disable_documents` (lá é o EVENTO de inutilização de faixa, com protocolo e
 * sem valor). O ERP chama as duas coisas de "inutilizada", e confundi-las é o
 * que faz os totais não baterem. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discarded_documents')) {
            return;
        }

        Schema::create('discarded_documents', function (Blueprint $table) {
            $table->id();
            $table->string('cnpj_cpf', 45);
            $table->integer('model');                          // 55 | 65
            $table->bigInteger('series')->nullable();
            $table->bigInteger('number')->nullable();
            // A NFC-e descartada não tem chave: o ERP grava o texto
            // "INUTILIZADA" no campo. Só entra com 44 dígitos.
            $table->string('key', 45)->nullable();
            $table->date('issue_dh')->nullable();
            $table->string('month_year', 6)->nullable();
            $table->double('value')->default(0);
            $table->string('situacao_erp', 4)->nullable();     // '5' | 'I' (como veio)
            $table->string('environment_type', 1)->nullable();
            // Dedup por empresa+modelo+série+número+competência: não dá para
            // usar a chave, que a NFC-e descartada não tem.
            $table->string('identidade', 191)->unique('discarded_documents_identidade_index');
            $table->timestamps();

            $table->index('cnpj_cpf');
            $table->index('issue_dh');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discarded_documents');
    }
};
