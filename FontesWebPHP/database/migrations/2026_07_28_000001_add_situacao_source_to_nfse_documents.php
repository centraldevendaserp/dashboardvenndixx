<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De onde veio a `situacao` da NFS-e: 'xml' (retorno do provedor) ou 'erp'.
 *
 * O XML não é fonte confiável: há nota cancelada no ERP cujo arquivo continua
 * dizendo "Emitida", porque o emissor nem sempre o regrava ao cancelar.
 *
 * Regra: ERP vence o XML — vinda do ERP, a situação não é rebaixada pelo
 * reimport. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfse_documents') || Schema::hasColumn('nfse_documents', 'situacao_source')) {
            return;
        }

        Schema::table('nfse_documents', function (Blueprint $table) {
            $table->string('situacao_source', 10)->nullable()->after('situacao');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('nfse_documents') && Schema::hasColumn('nfse_documents', 'situacao_source')) {
            Schema::table('nfse_documents', function (Blueprint $table) {
                $table->dropColumn('situacao_source');
            });
        }
    }
};
