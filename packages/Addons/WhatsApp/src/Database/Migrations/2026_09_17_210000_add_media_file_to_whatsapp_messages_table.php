<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The CRM keeps its own copy of every attachment it sends. WhatsApp is
     * not an archive — messages age out of the phone, and this business
     * needs the document trail attached to the Lead. `media_path` points at
     * the private disk; it is never served directly, only through the
     * authorised download route.
     */
    public function up()
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('media_path')->nullable()->after('media_type');
            $table->string('media_name')->nullable()->after('media_path');
            $table->string('media_mime')->nullable()->after('media_name');
        });
    }

    public function down()
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn(['media_path', 'media_name', 'media_mime']);
        });
    }
};
