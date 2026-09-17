<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What kind of attachment the message carried, when it carried one
     * ('image', 'video', 'audio', 'document', 'sticker', 'location',
     * 'contact'). The file itself isn't stored yet — this only records that
     * something was attached, so a media-only message stops disappearing
     * from the history entirely. Kept as a plain nullable string rather than
     * an enum so adding a kind later needs no migration.
     */
    public function up()
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('media_type')->nullable()->after('body');
        });
    }

    public function down()
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('media_type');
        });
    }
};
