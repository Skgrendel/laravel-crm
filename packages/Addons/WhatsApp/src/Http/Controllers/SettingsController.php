<?php

namespace Addons\WhatsApp\Http\Controllers;

use Addons\WhatsApp\Repositories\WhatsAppSettingRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\User\Repositories\UserRepository;

class SettingsController extends Controller
{
    public function __construct(
        protected WhatsAppSettingRepository $whatsAppSettingRepository,
        protected UserRepository $userRepository,
    ) {}

    /**
     * Display the WhatsApp integration settings. The QR/live-status card
     * (2.5) and chat panel (2.3) build on top of this same screen later —
     * for now (2.2) it only covers what passive lead capture needs: enabling
     * the addon, pointing it at this installation's microservice session,
     * the shared webhook secret, and a default owner for captured leads.
     */
    public function index(): View
    {
        $settings = $this->whatsAppSettingRepository->getSettings();

        $users = $this->userRepository->all();

        return view('whatsapp::settings.index', compact('settings', 'users'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled'          => 'sometimes|boolean',
            'session_id'       => 'nullable|string',
            'service_url'      => 'nullable|url',
            'default_owner_id' => 'nullable|exists:users,id',
        ]);

        $data['enabled'] = $request->boolean('enabled');

        $settings = $this->whatsAppSettingRepository->getSettings();

        $this->whatsAppSettingRepository->update($data, $settings->id);

        session()->flash('success', trans('whatsapp::app.settings.index.update-success'));

        return redirect()->route('admin.settings.whatsapp.index');
    }

    /**
     * Rotate the API key Krayin sends to the microservice. The admin must
     * then update `apiKey` for this session in the microservice's
     * `config/sessions.json` with the new one shown.
     */
    public function regenerateApiKey(): RedirectResponse
    {
        $this->whatsAppSettingRepository->regenerateApiKey();

        session()->flash('success', trans('whatsapp::app.settings.index.api-key-regenerated'));

        return redirect()->route('admin.settings.whatsapp.index');
    }

    /**
     * Rotate the webhook secret the microservice signs events with. The
     * admin must then update `webhookSecret` for this session in the
     * microservice's `config/sessions.json` with the new one shown.
     */
    public function regenerateWebhookSecret(): RedirectResponse
    {
        $this->whatsAppSettingRepository->regenerateWebhookSecret();

        session()->flash('success', trans('whatsapp::app.settings.index.webhook-secret-regenerated'));

        return redirect()->route('admin.settings.whatsapp.index');
    }
}
