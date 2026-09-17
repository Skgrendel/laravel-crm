<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns the unified inbox runs on.
     *
     * `last_inbound_at` / `last_outbound_at` are denormalised on purpose:
     * with both, "is this conversation waiting for us, and since when" is a
     * column comparison instead of a correlated subquery over the messages
     * table for every row of the list — and that list is the screen an agent
     * keeps open all day, sorted by exactly that.
     */
    public function up()
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->timestamp('last_inbound_at')->nullable()->after('last_message_at');
            $table->timestamp('last_outbound_at')->nullable()->after('last_inbound_at');

            /**
             * Read state lives on the conversation, not per user: a Lead has
             * one owner, so "unread" has one meaning. If two agents ever work
             * the same conversation this needs a pivot instead.
             */
            $table->timestamp('agent_last_read_at')->nullable()->after('last_outbound_at');

            /**
             * Seconds between the customer's first message and the first
             * reply. Stored rather than computed because it never changes
             * once set, and reports group by it.
             */
            $table->unsignedInteger('first_response_seconds')->nullable()->after('agent_last_read_at');

            $table->index(['last_inbound_at', 'last_outbound_at'], 'whatsapp_conversations_waiting_index');
        });

        $this->backfill();
    }

    /**
     * Existing conversations would otherwise show up as never having been
     * written to, which in an inbox sorted by waiting time is worse than
     * being absent.
     */
    protected function backfill(): void
    {
        $conversations = DB::table('whatsapp_conversations')->pluck('id');

        foreach ($conversations as $conversationId) {
            $messages = DB::table('whatsapp_messages')
                ->where('conversation_id', $conversationId)
                ->orderBy('sent_at')
                ->orderBy('id')
                ->get(['type', 'sent_at']);

            if ($messages->isEmpty()) {
                continue;
            }

            $inbound = $messages->where('type', 'received');
            $outbound = $messages->whereIn('type', ['sent_api', 'echo']);

            $firstInbound = $inbound->first();

            $firstReplyAfter = $firstInbound
                ? $outbound->first(fn ($m) => $m->sent_at > $firstInbound->sent_at)
                : null;

            DB::table('whatsapp_conversations')
                ->where('id', $conversationId)
                ->update([
                    'last_inbound_at' => $inbound->last()->sent_at ?? null,
                    'last_outbound_at' => $outbound->last()->sent_at ?? null,
                    'first_response_seconds' => $firstInbound && $firstReplyAfter
                        ? strtotime($firstReplyAfter->sent_at) - strtotime($firstInbound->sent_at)
                        : null,
                ]);
        }
    }

    public function down()
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropIndex('whatsapp_conversations_waiting_index');

            $table->dropColumn([
                'last_inbound_at',
                'last_outbound_at',
                'agent_last_read_at',
                'first_response_seconds',
            ]);
        });
    }
};
