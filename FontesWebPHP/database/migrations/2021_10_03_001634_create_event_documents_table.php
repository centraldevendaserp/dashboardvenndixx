<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('event_documents')) {
            return;
        }

        Schema::create('event_documents', function (Blueprint $table) {
            $table->id();
            $table->string('environment_type', 1)->nullable();
            $table->string('cnpj', 45)->nullable();
            $table->unsignedInteger('model')->nullable();
            $table->string('nfe_key', 50)->nullable();
            $table->timestamp('event_dh')->nullable();
            $table->string('event_type', 45)->nullable();
            $table->unsignedBigInteger('event_number')->nullable();
            $table->string('event_desc')->nullable();
            $table->string('event_status', 45)->nullable();
            $table->string('protocol_number', 45)->nullable();
            $table->string('justification')->nullable();
            $table->string('correction')->nullable();
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
        Schema::dropIfExists('event_documents');
    }
}
