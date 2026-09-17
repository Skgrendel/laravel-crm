<?php

namespace Addons\Zadarma\Services;

use Illuminate\Support\Facades\Http;

class ZadarmaClient
{
    /**
     * Zadarma API base URL.
     */
    protected string $baseUrl = 'https://api.zadarma.com';

    public function __construct(
        protected string $apiKey,
        protected string $apiSecret,
    ) {}

    /**
     * Retrieve the account balance. Used as a lightweight credential check
     * for the "Probar conexión" action, since it requires no parameters and
     * fails clearly on bad credentials.
     */
    public function getBalance(): array
    {
        return $this->get('/v1/info/balance/');
    }

    /**
     * Issue a temporary (72h) WebRTC key for a SIP extension, used to
     * initialize the browser softphone widget without ever exposing the
     * account's real API credentials to the browser.
     */
    public function getWebrtcKey(string $sipLogin): string
    {
        $data = $this->get('/v1/webrtc/get_key/', ['sip' => $sipLogin]);

        return $data['key'];
    }

    /**
     * Return the current call-forwarding (redirection) settings for a PBX
     * extension: what happens on no-answer/always — nothing, ring another
     * phone, or go to voicemail. This is the one PBX-admin resource that's
     * actually writable through Zadarma's API (confirmed against their
     * official SDK source — there's no API for SIP trunks or for changing
     * which extension a DID number rings, both of those are panel-only).
     */
    public function getPbxRedirection(string $extension): array
    {
        return $this->get('/v1/pbx/redirection/', ['pbx_number' => $extension]);
    }

    /**
     * Forward calls for an extension to a phone number.
     */
    public function setPbxPhoneRedirection(string $extension, string $destinationNumber, bool $always, bool $setCallerId): array
    {
        return $this->post('/v1/pbx/redirection/', [
            'pbx_number'    => $extension,
            'type'          => 'phone',
            'condition'     => $always ? 'always' : 'noanswer',
            'destination'   => $destinationNumber,
            'set_caller_id' => $setCallerId ? 'on' : 'off',
        ]);
    }

    /**
     * Forward calls for an extension to voicemail (email delivery).
     */
    public function setPbxVoicemailRedirection(string $extension, string $destinationEmail, bool $always): array
    {
        return $this->post('/v1/pbx/redirection/', [
            'pbx_number' => $extension,
            'type'       => 'voicemail',
            'condition'  => $always ? 'always' : 'noanswer',
            'destination' => $destinationEmail,
        ]);
    }

    /**
     * Turn off call forwarding for an extension.
     */
    public function setPbxRedirectionOff(string $extension): array
    {
        return $this->post('/v1/pbx/redirection/', [
            'pbx_number' => $extension,
            'status'     => 'off',
        ]);
    }

    /**
     * The account's purchased DID numbers, including which SIP extension
     * each currently rings (read-only — Zadarma's API has no endpoint to
     * change that assignment, it's set in their panel).
     */
    public function getDirectNumbers(): array
    {
        $data = $this->get('/v1/direct_numbers/');

        return $data['info'] ?? [];
    }

    /**
     * Perform a signed GET request against the Zadarma API.
     */
    public function get(string $method, array $params = []): array
    {
        return $this->request('GET', $method, $params);
    }

    /**
     * Perform a signed POST request against the Zadarma API.
     */
    public function post(string $method, array $params = []): array
    {
        return $this->request('POST', $method, $params);
    }

    /**
     * Build the Zadarma signature and perform the HTTP request.
     *
     * Signature algorithm (per Zadarma API docs): base64(hmac_sha1(
     *   method . http_build_query(params) . md5(http_build_query(params)),
     *   api_secret
     * )).
     */
    protected function request(string $httpMethod, string $method, array $params = []): array
    {
        ksort($params);

        $queryString = http_build_query($params);

        $signatureString = $method.$queryString.md5($queryString);

        $signature = base64_encode(hash_hmac('sha1', $signatureString, $this->apiSecret));

        $authHeader = "{$this->apiKey}:{$signature}";

        $request = Http::withHeaders([
            'Authorization' => $authHeader,
        ]);

        $response = $httpMethod === 'GET'
            ? $request->get($this->baseUrl.$method, $params)
            : $request->asForm()->post($this->baseUrl.$method, $params);

        $response->throw();

        $data = $response->json();

        if (($data['status'] ?? null) === 'error') {
            throw new \RuntimeException($data['message'] ?? 'Zadarma API error');
        }

        return $data;
    }
}
