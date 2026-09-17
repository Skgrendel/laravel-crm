<?php

namespace Addons\WhatsApp\Http\Controllers;

use Addons\WhatsApp\Repositories\WhatsAppConversationRepository;
use Addons\WhatsApp\Services\WhatsAppLeadCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * The unified inbox (Fase 3.2).
 *
 * The chat panel answers "what did we say to this Lead". This answers the
 * question an agent actually starts the day with: "who is waiting for me,
 * and for how long". Sorted by waiting time rather than recency — a
 * conversation nobody is waiting on is not urgent however recent it is.
 */
class InboxController extends Controller
{
    public function __construct(
        protected WhatsAppConversationRepository $whatsAppConversationRepository,
    ) {}

    public function index(): View
    {
        return view('whatsapp::inbox.index');
    }

    public function list(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'scope' => 'nullable|in:mine,unassigned,all',
        ]);

        $scope = $filters['scope'] ?? 'mine';

        $conversations = $this->whatsAppConversationRepository
            ->getModel()
            ->newQuery()
            ->with(['lead.user', 'person'])
            ->whereNotNull('last_message_at')
            ->get();

        $currentUserId = auth()->guard('user')->id();

        /**
         * Scoped exactly like the rest of the CRM: an agent restricted to
         * their own records must not see a colleague's conversations here
         * either. Unassigned ones stay visible — somebody has to be able to
         * pick them up.
         */
        $authorizedUserIds = bouncer()->getAuthorizedUserIds();

        $conversations = $conversations
            ->filter(function ($conversation) use ($authorizedUserIds) {
                if (! $authorizedUserIds) {
                    return true;
                }

                $ownerId = $conversation->lead?->user_id;

                return $ownerId === null || in_array($ownerId, $authorizedUserIds);
            })
            ->filter(function ($conversation) use ($scope, $currentUserId) {
                return match ($scope) {
                    'mine' => $conversation->lead?->user_id === $currentUserId,
                    'unassigned' => $conversation->lead?->user_id === null,
                    default => true,
                };
            });

        /**
         * Waiting conversations first, longest wait at the top; everything
         * else by recency underneath.
         */
        $sorted = $conversations->sortBy(function ($conversation) {
            return $conversation->isWaiting()
                ? [0, $conversation->last_inbound_at?->timestamp]
                : [1, -($conversation->last_message_at?->timestamp ?? 0)];
        })->values();

        return response()->json([
            'conversations' => $sorted->map(fn ($conversation) => [
                'id' => $conversation->id,
                'lead_id' => $conversation->lead_id,
                'name' => $conversation->person?->name ?: $conversation->phone_number,
                'phone' => $conversation->phone_number,
                'is_hidden_number' => str_ends_with((string) $conversation->remote_jid, '@lid'),
                'owner' => $conversation->lead?->user?->name,
                'is_waiting' => $conversation->isWaiting(),
                'is_unread' => $conversation->isUnread(),
                'waiting_since' => $conversation->isWaiting()
                    ? $conversation->last_inbound_at?->toIso8601String()
                    : null,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'first_response_seconds' => $conversation->first_response_seconds,
            ])->values(),
        ]);
    }

    /**
     * An agent takes an unassigned conversation.
     *
     * These have messages but no Lead — capture could not resolve an owner,
     * or the Lead was deleted later — which makes them invisible to every
     * scoped view and impossible to reply to. Claiming gives the
     * conversation a Lead owned by the agent, and returns its id so the
     * inbox can open the thread straight away.
     */
    public function claim(int $conversationId): JsonResponse
    {
        $conversation = $this->whatsAppConversationRepository
            ->getModel()
            ->newQuery()
            ->find($conversationId);

        abort_if(! $conversation, 404);

        if ($conversation->lead_id) {
            return response()->json([
                'message' => trans('whatsapp::app.inbox.already-assigned'),
            ], 422);
        }

        $lead = app(WhatsAppLeadCreator::class)->claimForUser(
            $conversation,
            auth()->guard('user')->id()
        );

        if (! $lead) {
            return response()->json([
                'message' => trans('whatsapp::app.inbox.claim-failed'),
            ], 422);
        }

        return response()->json(['lead_id' => $lead->id]);
    }

    /**
     * Called when an agent opens a conversation from the inbox, so the
     * unread marker clears without waiting for them to reply.
     */
    public function markRead(int $conversationId): JsonResponse
    {
        $conversation = $this->whatsAppConversationRepository
            ->getModel()
            ->newQuery()
            ->with('lead')
            ->find($conversationId);

        abort_if(! $conversation, 404);

        $authorizedUserIds = bouncer()->getAuthorizedUserIds();
        $ownerId = $conversation->lead?->user_id;

        abort_if(
            $authorizedUserIds && $ownerId !== null && ! in_array($ownerId, $authorizedUserIds),
            403
        );

        $this->whatsAppConversationRepository->markAsRead($conversationId);

        return response()->json(['read' => true]);
    }
}
