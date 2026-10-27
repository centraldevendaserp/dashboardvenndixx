<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Descarte não tem ambiente: o ERP não guarda tpAmb e a nota nunca foi
 * transmitida à SEFAZ, então gravar '1' era palpite — e "Produção" é justamente
 * a leitura que faz o contador tratar o valor como venda real.
 *
 * A coluna nula segue a convenção do portal (NULL conta como produção nos
 * FILTROS), mas a listagem mostra "—" com a explicação.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Só as linhas do palpite antigo: fonte que venha a saber o tpAmb grava
        // '1'/'2' depois desta migration.
        DB::table('discarded_documents')->where('environment_type', '1')->update([
            'environment_type' => null,
        ]);
    }

    public function down(): void
    {
        DB::table('discarded_documents')->whereNull('environment_type')->update([
            'environment_type' => '1',
        ]);
    }
};
