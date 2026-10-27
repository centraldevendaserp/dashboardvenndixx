<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFantasyName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Idempotente: no dump de producao a coluna ja existe -> no-op.
        if (! Schema::hasTable('companies') || Schema::hasColumn('companies', 'fantasy_name')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->string('fantasy_name')
                ->nullable()
                ->after('corporate_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('fantasy_name');
        });
    }
}
