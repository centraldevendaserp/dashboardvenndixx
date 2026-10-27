<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela própria da NFS-e, separada de `documents` de propósito: status TEXTUAL
 * (sem cStat), chave opcional e layout que varia por prefeitura. Uma tabela
 * cobre nacional e municipal pelo discriminador `padrao` + `municipio`.
 *
 * Dedup pela coluna derivada `identidade` (UNIQUE): nacional = a chave;
 * municipal = chave lógica composta. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfse_documents')) {
            return;
        }

        Schema::create('nfse_documents', function (Blueprint $table) {
            $table->id();
            $table->string('padrao', 20);                    // 'nacional' | 'municipal'
            $table->string('cnpj_prestador', 45);
            $table->string('ie', 45)->nullable();
            $table->string('cnpj_cpf_tomador', 45)->nullable();
            $table->string('municipio', 60)->nullable();     // código IBGE ou nome
            $table->string('numero', 60)->nullable();        // pode ser alfanumérico
            $table->string('serie', 20)->nullable();
            $table->string('cod_verificacao', 60)->nullable();
            $table->string('chave', 60)->nullable();         // nacional: chave de 50 dígitos
            $table->string('identidade', 191)->unique('nfse_documents_identidade_index');
            $table->string('month_year', 6)->nullable();
            $table->date('issue_dh')->nullable();
            $table->string('situacao', 40)->nullable();      // status TEXTUAL (Autorizada/Cancelada/Substituída...)
            $table->string('protocol', 80)->nullable();
            $table->string('environment_type', 1)->nullable();
            $table->double('valor')->default(0);
            $table->double('size')->nullable();
            $table->longText('path_xml')->nullable();
            $table->timestamps();

            $table->index('cnpj_prestador');
            $table->index('issue_dh');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfse_documents');
    }
};
