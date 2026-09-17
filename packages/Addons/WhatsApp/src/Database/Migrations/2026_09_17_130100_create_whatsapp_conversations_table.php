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
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->increments('id');

            /**
             * WhatsApp's own id for the chat (e.g. "549351...@s.whatsapp.net")
             * — stable and unique per contact, used to dedupe on repeat
             * webhook deliveries.
             */
            $table->string('remote_jid')->unique();

            /** Digits-only phone, extracted from `remote_jid`, for lookups. */
            $table->string('phone_number')->index();

            $table->unsignedInteger('person_id')->nullable();
            $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();

            $table->unsignedInteger('lead_id')->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();

            $table->timestamp('last_message_at')->nullable();

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
        Schema::dropIfExists('whatsapp_conversations');
    }
};
