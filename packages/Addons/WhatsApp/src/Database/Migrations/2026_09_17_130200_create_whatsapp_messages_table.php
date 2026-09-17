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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('conversation_id');
            $table->foreign('conversation_id')->references('id')->on('whatsapp_conversations')->cascadeOnDelete();

            /**
             * WhatsApp's own message id — the dedup key for retried webhook
             * deliveries from the microservice.
             */
            $table->string('wa_message_id')->unique();

            /**
             * received  = real inbound message from the customer
             * sent_api  = sent from the CRM via the microservice's /send
             * echo      = sent from the agent's phone app directly, reflected
             *             back by WhatsApp for history only — never treated
             *             as inbound (see docs/whatsapp-addon.md).
             */
            $table->enum('type', ['received', 'sent_api', 'echo']);

            $table->text('body')->nullable();
            $table->timestamp('sent_at');

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
        Schema::dropIfExists('whatsapp_messages');
    }
};
