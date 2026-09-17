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
