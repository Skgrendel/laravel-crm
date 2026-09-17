<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Automatic assignment (Fase 3.3).
     *
     * Until now every captured Lead landed on `default_owner_id` and somebody
     * redistributed by hand, which does not scale past one agent.
     * `default_owner_id` stays as the fallback: a pool can end up empty
     * (everybody deactivated, nobody selected yet) and a Lead with no owner
     * is worse than one on the wrong desk.
     */
    public function up()
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->string('assignment_mode')->default('fixed')->after('default_owner_id');

            $table->json('assignment_user_ids')->nullable()->after('assignment_mode');

            /**
             * Where round-robin left off. Stored rather than derived from
             * the leads table so the rotation survives leads being deleted
             * or reassigned by hand.
             */
            $table->unsignedInteger('assignment_cursor')->default(0)->after('assignment_user_ids');
        });
    }

    public function down()
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->dropColumn(['assignment_mode', 'assignment_user_ids', 'assignment_cursor']);
        });
    }
};
