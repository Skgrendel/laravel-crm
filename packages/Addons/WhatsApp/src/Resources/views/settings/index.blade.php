<x-admin::layouts>
    <x-slot:title>
        @lang('whatsapp::app.settings.index.title')
    </x-slot>

    <x-admin::form
        :action="route('admin.settings.whatsapp.update')"
        method="PUT"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.whatsapp" />

                    <div class="text-xl font-bold dark:text-white">
                        @lang('whatsapp::app.settings.index.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <x-admin::button
                        button-type="submit"
                        class="primary-button"
                        :title="trans('whatsapp::app.settings.index.save-btn')"
                    />
                </div>
            </div>

            <!-- Enabled toggle card -->
            <div class="flex items-center justify-between rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-2.5">
                    <span class="icon-mail text-2xl dark:text-white"></span>

                    <span class="text-base font-semibold text-gray-800 dark:text-white">
                        WhatsApp
                    </span>
                </div>

                <x-admin::form.control-group class="!mb-0 flex items-center gap-4">
                    <x-admin::form.control-group.label class="!mb-0">
                        @lang('whatsapp::app.settings.index.enabled')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="switch"
                        class="cursor-pointer"
                        name="enabled"
                        id="enabled"
                        value="1"
                        for="enabled"
                        :checked="(boolean) $settings->enabled"
                        :label="trans('whatsapp::app.settings.index.enabled')"
                    />
                </x-admin::form.control-group>
            </div>

            <!-- Session status card -->
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-2 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('whatsapp::app.settings.index.status-title')
                </p>

                <span
                    class="rounded-full px-3 py-1 text-xs font-semibold {{ $settings->last_status === 'connected' ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}"
                >
                    @if ($settings->last_status === 'connected')
                        {{ trans('whatsapp::app.settings.index.status-connected', ['number' => $settings->connected_number]) }}
                    @elseif ($settings->last_status === 'disconnected')
                        @lang('whatsapp::app.settings.index.status-disconnected')
                    @else
                        @lang('whatsapp::app.settings.index.status-unknown')
                    @endif
                </span>
            </div>

            <!-- Microservice connection card -->
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('whatsapp::app.settings.index.connection-title')
                </p>

                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    @lang('whatsapp::app.settings.index.connection-info')
                </p>

                <div class="flex flex-col gap-4">
                    <div class="flex gap-4 max-sm:flex-col">
                        <x-admin::form.control-group class="flex-1">
                            <x-admin::form.control-group.label>
                                @lang('whatsapp::app.settings.index.session-id')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                id="session_id"
                                name="session_id"
                                :value="$settings->session_id"
                                :label="trans('whatsapp::app.settings.index.session-id')"
                                :placeholder="trans('whatsapp::app.settings.index.session-id-placeholder')"
                            />
                        </x-admin::form.control-group>

                        <x-admin::form.control-group class="flex-1">
                            <x-admin::form.control-group.label>
                                @lang('whatsapp::app.settings.index.service-url')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                id="service_url"
                                name="service_url"
                                :value="$settings->service_url"
                                :label="trans('whatsapp::app.settings.index.service-url')"
                                placeholder="https://baileys.example.com"
                            />
                        </x-admin::form.control-group>
                    </div>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('whatsapp::app.settings.index.api-key')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="password"
                            id="api_key"
                            name="api_key"
                            :label="trans('whatsapp::app.settings.index.api-key')"
                            placeholder="{{ $settings->api_key ? '••••••••' : '' }}"
                        />
                    </x-admin::form.control-group>
                </div>
            </div>

            <!-- Webhook secret card -->
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('whatsapp::app.settings.index.webhook-title')
                </p>

                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    @lang('whatsapp::app.settings.index.webhook-info')
                </p>

                @if ($settings->webhook_secret)
                    <input
                        type="text"
                        class="control h-11 w-full rounded-md border px-3 py-2.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                        value="{{ $settings->webhook_secret }}"
                        readonly
                        onclick="this.select()"
                    />
                @else
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @lang('whatsapp::app.settings.index.webhook-secret-hidden')
                    </p>
                @endif
            </div>

            <!-- Lead capture card -->
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('whatsapp::app.settings.index.lead-capture-title')
                </p>

                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    @lang('whatsapp::app.settings.index.lead-capture-info')
                </p>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('whatsapp::app.settings.index.default-owner')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="select"
                        id="default_owner_id"
                        name="default_owner_id"
                        :label="trans('whatsapp::app.settings.index.default-owner')"
                    >
                        <option value="">—</option>

                        @foreach ($users as $user)
                            <option
                                value="{{ $user->id }}"
                                @selected($settings->default_owner_id == $user->id)
                            >{{ $user->name }}</option>
                        @endforeach
                    </x-admin::form.control-group.control>
                </x-admin::form.control-group>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
