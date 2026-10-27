<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('documents')) {
            return;
        }

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('cnpj_cpf', 45);
            $table->string('ie', 45);
            $table->unsignedInteger('model');
            $table->unsignedBigInteger('series');
            $table->unsignedBigInteger('number');
            $table->string('key', 80);
            $table->string('month_year', 6);
            $table->date('issue_dh');
            $table->longText('path_xml');
            $table->string('protocol', 80);
            $table->string('environment_type', 1);
            $table->string('status_xml', 10);
            $table->double('size')->nullable();
            $table->double('vNF');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('documents');
    }
}
