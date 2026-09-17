<?php

namespace Addons\Zadarma\Http\Controllers;

use Addons\Zadarma\Repositories\ZadarmaSettingRepository;
use Addons\Zadarma\Services\CallActivityRecorder;
use Addons\Zadarma\Services\ZadarmaWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Webkul\Admin\Http\Controllers\Controller;

class WebhookController extends Controller
{
    /**
     * Zadarma webhook event types that represent a finished call and carry
     * duration/disposition, i.e. the ones worth turning into an activity.
     */
    protected const FINISHED_CALL_EVENTS = ['NOTIFY_END', 'NOTIFY_OUT_END'];

    public function __construct(
        protected ZadarmaSettingRepository $zadarmaSettingRepository,
        protected CallActivityRecorder $callActivityRecorder,
    ) {}

    /**
     * Handle both Zadarma's one-time webhook URL verification (GET with a
     * `zd_echo` query param) and the actual event notifications (POST,
     * application/x-www-form-urlencoded).
     */
    public function handle(Request $request, string $secret): Response
    {
        $settings = $this->zadarmaSettingRepository->getSettings();

        /**
         * `hash_equals` (rather than `===`) to avoid leaking timing
         * information about how much of the secret matched.
         */
        if (! $settings->enabled || ! hash_equals((string) $settings->webhook_secret, $secret)) {
            abort(404);
        }

        if ($request->filled('zd_echo')) {
            return response((string) $request->query('zd_echo'));
        }

        $event = $request->input('event');
        $pbxCallId = $request->input('pbx_call_id');

        if (! in_array($event, self::FINISHED_CALL_EVENTS, true) || empty($pbxCallId)) {
            return response()->noContent();
        }

        if (! $this->hasValidSignature($request, $event, $settings->api_secret)) {
            Log::warning('Zadarma webhook rejected: signature mismatch.', [
                'event'       => $event,
                'pbx_call_id' => $pbxCallId,
            ]);

            abort(403);
        }

        $isOutgoing = $event === 'NOTIFY_OUT_END';

        $this->callActivityRecorder->record([
            'pbx_call_id'         => $pbxCallId,
            'direction'           => $isOutgoing ? 'outgoing' : 'incoming',
            'caller_id'           => $request->input('caller_id'),
            'called_number'       => $isOutgoing ? $request->input('destination') : $request->input('called_did'),
            'internal_extension'  => $request->input('internal'),
            'duration'            => $request->filled('duration') ? (int) $request->input('duration') : null,
            'disposition'         => $request->input('disposition'),
            'is_recorded'         => $request->boolean('is_recorded'),
            'call_id_with_rec'    => $request->input('call_id_with_rec'),
            'call_start'          => $request->input('call_start'),
        ]);

        return response()->noContent();
    }

    /**
     * Verify the `Signature` header against the payload, using the field
     * combination Zadarma signs for this specific event type.
     */
    protected function hasValidSignature(Request $request, string $event, ?string $apiSecret): bool
    {
        $signatureHeader = $request->header('Signature');

        if (empty($signatureHeader) || empty($apiSecret)) {
            return false;
        }

        $payload = $request->all();

        $signatureString = $event === 'NOTIFY_OUT_END'
            ? ZadarmaWebhookSignature::outgoingSignatureString($payload)
            : ZadarmaWebhookSignature::incomingSignatureString($payload);

        return ZadarmaWebhookSignature::verify($signatureString, $signatureHeader, $apiSecret);
    }
}
