<?php

namespace Addons\WhatsApp\Services;

use Illuminate\Support\Facades\Http;

/**
 * Talks to this installation's own session on the baileys-whatsapp-service
 * microservice (separate repo — see its README for the full contract).
 * Endpoints and response shapes below are copied from that README verbatim,
 * not guessed.
 */
class WhatsAppClient
{
    public function __construct(
        protected string $serviceUrl,
        protected string $sessionId,
        protected string $apiKey,
    ) {}

    /**
     * `{ id, label, status, number, updatedAt }`, `status` one of
     * `connecting | qr | connected | disconnected`.
     */
    public function getStatus(): array
    {
        return $this->get("/sessions/{$this->sessionId}/status");
    }

    /**
     * `{ qr: "data:image/png;base64,..." }` when the session's status is
     * `qr`. Throws (via `throw()`) on the 409 the service returns for any
     * other status — callers should check `getStatus()` first.
     */
    public function getQr(): string
    {
        $data = $this->get("/sessions/{$this->sessionId}/qr");

        return $data['qr'];
    }

    /**
     * Restart the session — forces a new QR if credentials were invalidated
     * (e.g. unlinked from the phone).
     */
    public function reconnect(): void
    {
        $this->post("/sessions/{$this->sessionId}/reconnect");
    }

    /**
     * Unlinks the currently connected number (a real WhatsApp-side logout,
     * not just a local reconnect) and starts a fresh session — for
     * switching to a different phone number without needing the old phone
     * in hand to unlink it first.
     */
    public function logout(): void
    {
        $this->post("/sessions/{$this->sessionId}/logout");
    }

    protected function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    protected function post(string $path): array
    {
        return $this->request('POST', $path);
    }

    protected function request(string $method, string $path): array
    {
        $response = Http::withHeaders(['X-Api-Key' => $this->apiKey])
            ->timeout(10)
            ->send($method, rtrim($this->serviceUrl, '/').$path);

        $response->throw();

        return $response->json() ?? [];
    }
}
