<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * WhatsApp chat panel (Addons\WhatsApp, Fase 2.3): only agents who can see
 * the Lead itself may listen for its live messages. Mirrors the same
 * ownership/data-scope check the Lead view route already applies
 * (LeadController::view -> bouncer()->getAuthorizedUserIds()), so a sales
 * rep can't eavesdrop on a colleague's conversation by guessing the
 * channel name.
 */
Broadcast::channel('whatsapp.lead.{leadId}', function ($user, $leadId) {
    $lead = app(Webkul\Lead\Repositories\LeadRepository::class)->find($leadId);

    if (! $lead) {
        return false;
    }

    $authorizedUserIds = bouncer()->getAuthorizedUserIds();

    return ! $authorizedUserIds || in_array($lead->user_id, $authorizedUserIds);
});
