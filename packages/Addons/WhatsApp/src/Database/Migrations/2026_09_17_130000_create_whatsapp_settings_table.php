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
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('enabled')->default(false);

            /**
             * Which Baileys session (in the separate baileys-whatsapp-service
             * repo's config/sessions.json) this Krayin installation talks to
             * — e.g. "proderi" or "acoficum". One installation, one session.
             */
            $table->string('session_id')->nullable();

            $table->string('service_url')->nullable();
            $table->text('api_key')->nullable();
            $table->text('webhook_secret')->nullable();

            /**
             * New leads captured passively from WhatsApp need an owner
             * (leads.user_id is NOT NULL) but there's no agent context in an
             * unauthenticated webhook request — this is who they land on
             * until reassigned.
             */
            $table->unsignedInteger('default_owner_id')->nullable();
            $table->foreign('default_owner_id')->references('id')->on('users')->nullOnDelete();

            /**
             * Cached from the microservice's session.connected/disconnected
             * webhook events, for the settings screen's status card (2.5) —
             * avoids polling the microservice just to render the page.
             */
            $table->string('last_status')->nullable();
            $table->string('connected_number')->nullable();

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
        Schema::dropIfExists('whatsapp_settings');
    }
};
