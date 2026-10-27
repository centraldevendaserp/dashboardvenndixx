<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDisableDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('disable_documents')) {
            return;
        }

        Schema::create('disable_documents', function (Blueprint $table) {
            $table->id();
            $table->string('environment_type', 1)->nullable();
            $table->string('service', 45)->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('year', 45)->nullable();
            $table->string('cnpj', 45)->nullable();
            $table->unsignedInteger('model')->nullable();
            $table->unsignedBigInteger('series')->nullable();
            $table->unsignedBigInteger('number_start')->nullable();
            $table->unsignedBigInteger('number_end')->nullable();
            $table->timestamp('event_dh')->nullable();
            $table->string('event_status', 45)->nullable();
            $table->string('protocol_number', 45)->nullable();
            $table->string('justification')->nullable();
            $table->double('size')->nullable();
            $table->text('path_xml')->nullable();
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
        Schema::dropIfExists('disable_documents');
    }
}
