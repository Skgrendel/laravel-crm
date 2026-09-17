<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('zadarma_call_logs', function (Blueprint $table) {
            $table->increments('id');

            /**
             * Zadarma's own call identifier. Unique so retried webhook
             * deliveries for the same call don't create duplicate activities.
             */
            $table->string('pbx_call_id')->unique();

            $table->enum('direction', ['incoming', 'outgoing']);
            $table->string('caller_id')->nullable();
            $table->string('called_number')->nullable();
            $table->string('internal_extension')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('disposition')->nullable();
            $table->boolean('is_recorded')->default(false);
            $table->string('call_id_with_rec')->nullable();

            $table->unsignedInteger('lead_id')->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');

            $table->unsignedInteger('activity_id')->nullable();
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('set null');

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
        Schema::dropIfExists('zadarma_call_logs');
    }
};
