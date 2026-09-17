<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Repairs rows written before `WebhookController` passed the app
     * timezone to `Carbon::createFromTimestamp()`.
     *
     * Those inbound messages were stored as a UTC wall clock in a column
     * that carries no timezone, so Eloquent read them back as app-timezone
     * time and every one of them landed the app's UTC offset early. Messages
     * sent from the CRM used `now()` and were always right, so within a
     * single thread inbound and outbound drifted apart and the conversation
     * no longer read in order.
     *
     * Only rows that exist right now are touched — anything written after
     * this runs already goes through the fixed code path. The shift is
     * derived per row rather than hardcoded, so it stays correct across DST
     * and whatever `app.timezone` is set to.
     */
    public function up()
    {
        $this->shift(fn (Carbon $stored) => $stored->shiftTimezone('UTC')->setTimezone(config('app.timezone')));
    }

    public function down()
    {
        $this->shift(fn (Carbon $stored) => $stored->shiftTimezone(config('app.timezone'))->setTimezone('UTC'));
    }

    /**
     * `sent_api` is excluded: those rows are written by ChatController with
     * `now()`, which was never affected.
     */
    protected function shift(callable $convert): void
    {
        DB::table('whatsapp_messages')
            ->whereIn('type', ['received', 'echo'])
            ->orderBy('id')
            ->each(function ($message) use ($convert) {
                $corrected = $convert(Carbon::parse($message->sent_at));

                DB::table('whatsapp_messages')
                    ->where('id', $message->id)
                    ->update(['sent_at' => $corrected->format('Y-m-d H:i:s')]);
            });
    }
};
