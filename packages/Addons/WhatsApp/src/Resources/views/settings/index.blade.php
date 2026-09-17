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

            <!-- Session status + QR card -->
            <v-whatsapp-session
                is-configured="{{ ($settings->session_id && $settings->service_url && $settings->api_key) ? '1' : '' }}"
                cached-status="{{ $settings->last_status }}"
                cached-number="{{ $settings->connected_number }}"
            ></v-whatsapp-session>

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

                        <v-whatsapp-secret-field
                            value="{{ $settings->api_key }}"
                            regenerate-url="{{ route('admin.settings.whatsapp.api_key.regenerate') }}"
                        ></v-whatsapp-secret-field>
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

                <v-whatsapp-secret-field
                    value="{{ $settings->webhook_secret }}"
                    regenerate-url="{{ route('admin.settings.whatsapp.webhook_secret.regenerate') }}"
                ></v-whatsapp-secret-field>
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

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-whatsapp-secret-field-template"
        >
            <div class="flex items-center gap-2.5 max-sm:flex-col max-sm:items-stretch">
                <input
                    type="text"
                    class="control h-11 flex-1 rounded-md border px-3 py-2.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                    :value="value"
                    readonly
                    @click="$event.target.select()"
                />

                <button
                    type="button"
                    class="secondary-button"
                    @click="copyValue"
                >
                    @{{ copyLabel }}
                </button>

                <button
                    type="button"
                    class="secondary-button"
                    :disabled="isRegenerating"
                    @click="regenerate"
                >
                    @{{ regenerateLabel }}
                </button>
            </div>
        </script>

        <script type="module">
            app.component('v-whatsapp-secret-field', {
                template: '#v-whatsapp-secret-field-template',

                props: {
                    value: String,
                    regenerateUrl: String,
                },

                data() {
                    return {
                        isRegenerating: false,
                        copyLabel: @json(trans('whatsapp::app.settings.index.copy-btn')),
                        regenerateLabel: @json(trans('whatsapp::app.settings.index.regenerate-btn')),
                    };
                },

                methods: {
                    copyValue() {
                        navigator.clipboard.writeText(this.value);

                        this.$emitter.emit('add-flash', { type: 'success', message: @json(trans('whatsapp::app.settings.index.copy-success')) });
                    },

                    regenerate() {
                        this.$emitter.emit('open-confirm-modal', {
                            agree: () => {
                                this.isRegenerating = true;

                                this.$axios.post(this.regenerateUrl)
                                    .then(() => {
                                        window.location.reload();
                                    })
                                    .catch((error) => {
                                        this.isRegenerating = false;

                                        this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                                    });
                            },
                        });
                    },
                },
            });
        </script>

        <script
            type="text/x-template"
            id="v-whatsapp-session-template"
        >
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-base font-semibold text-gray-800 dark:text-white">
                        @lang('whatsapp::app.settings.index.status-title')
                    </p>

                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            class="secondary-button"
                            :disabled="loadingStatus"
                            @click="checkStatus"
                        >
                            @{{ checkStatusLabel }}
                        </button>

                        <button
                            v-if="isConfigured"
                            type="button"
                            class="secondary-button"
                            :disabled="reconnecting"
                            @click="reconnect"
                        >
                            @{{ reconnectLabel }}
                        </button>
                    </div>
                </div>

                <span
                    class="rounded-full px-3 py-1 text-xs font-semibold"
                    :class="badgeClass"
                >
                    @{{ statusLabel }}
                </span>

                <p
                    v-if="! isConfigured"
                    class="mt-3 text-xs text-gray-500 dark:text-gray-400"
                >
                    @{{ notConfiguredLabel }}
                </p>

                <div
                    v-if="status === 'qr'"
                    class="mt-4 flex flex-col items-center gap-2 border-t border-gray-100 pt-4 dark:border-gray-800"
                >
                    <p class="text-sm font-semibold text-gray-800 dark:text-white">
                        @lang('whatsapp::app.settings.index.qr-title')
                    </p>

                    <p class="max-w-xs text-center text-xs text-gray-500 dark:text-gray-400">
                        @lang('whatsapp::app.settings.index.qr-info')
                    </p>

                    <img
                        v-if="qr"
                        :src="qr"
                        class="h-48 w-48 rounded-md border border-gray-200 dark:border-gray-700"
                    />

                    <div
                        v-else
                        class="flex h-48 w-48 items-center justify-center rounded-md border border-dashed border-gray-300 text-xs text-gray-400 dark:border-gray-700"
                    >
                        @{{ qrLoadingLabel }}
                    </div>

                    <p class="text-xs text-gray-400">
                        @{{ refreshingInLabel }}
                    </p>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-whatsapp-session', {
                template: '#v-whatsapp-session-template',

                props: {
                    isConfigured: Boolean,
                    cachedStatus: String,
                    cachedNumber: String,
                },

                data() {
                    return {
                        status: this.cachedStatus || null,
                        number: this.cachedNumber || null,
                        qr: null,
                        loadingStatus: false,
                        reconnecting: false,
                        qrSecondsLeft: 20,
                        statusPollTimer: null,
                        qrRefreshTimer: null,
                        qrCountdownTimer: null,
                        checkStatusLabel: @json(trans('whatsapp::app.settings.index.check-status-btn')),
                        reconnectLabel: @json(trans('whatsapp::app.settings.index.reconnect-btn')),
                        notConfiguredLabel: @json(trans('whatsapp::app.settings.index.not-configured')),
                        qrLoadingLabel: @json(trans('whatsapp::app.settings.index.qr-loading')),
                        connectedLabelTemplate: @json(trans('whatsapp::app.settings.index.status-connected', ['number' => '__NUMBER__'])),
                        qrErrorTemplate: @json(trans('whatsapp::app.settings.index.qr-error', ['error' => '__ERROR__'])),
                    };
                },

                computed: {
                    statusLabel() {
                        if (this.status === 'connected') {
                            return this.connectedLabelTemplate.replace('__NUMBER__', this.number || '');
                        }

                        const labels = {
                            disconnected: @json(trans('whatsapp::app.settings.index.status-disconnected')),
                            connecting: @json(trans('whatsapp::app.settings.index.status-connecting')),
                            qr: @json(trans('whatsapp::app.settings.index.status-qr')),
                        };

                        return labels[this.status] || @json(trans('whatsapp::app.settings.index.status-unknown'));
                    },

                    badgeClass() {
                        if (this.status === 'connected') {
                            return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
                        }

                        if (this.status === 'qr' || this.status === 'connecting') {
                            return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300';
                        }

                        return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300';
                    },

                    refreshingInLabel() {
                        return @json(trans('whatsapp::app.settings.index.qr-refreshing-in', ['seconds' => '__N__'])).replace('__N__', this.qrSecondsLeft);
                    },
                },

                mounted() {
                    if (this.isConfigured) {
                        this.checkStatus();
                        this.statusPollTimer = setInterval(this.checkStatus, 5000);
                    }
                },

                beforeUnmount() {
                    clearInterval(this.statusPollTimer);
                    this.stopQrRefresh();
                },

                methods: {
                    checkStatus() {
                        this.loadingStatus = true;

                        this.$axios.get("{{ route('admin.settings.whatsapp.session.status') }}")
                            .then((response) => {
                                this.loadingStatus = false;
                                this.status = response.data.status;
                                this.number = response.data.number;

                                if (this.status === 'qr') {
                                    this.startQrRefresh();
                                } else {
                                    this.stopQrRefresh();
                                }
                            })
                            .catch(() => {
                                this.loadingStatus = false;
                            });
                    },

                    /**
                     * The microservice's README doesn't expose a QR expiry
                     * timestamp, so this just re-fetches on a fixed cadence
                     * (WhatsApp/Baileys typically rotates the code every
                     * ~20-60s) rather than tracking a precise server TTL.
                     */
                    startQrRefresh() {
                        if (this.qrRefreshTimer) {
                            return;
                        }

                        this.fetchQr();

                        this.qrRefreshTimer = setInterval(() => this.fetchQr(), 20000);

                        this.qrSecondsLeft = 20;
                        this.qrCountdownTimer = setInterval(() => {
                            this.qrSecondsLeft = this.qrSecondsLeft > 0 ? this.qrSecondsLeft - 1 : 20;
                        }, 1000);
                    },

                    stopQrRefresh() {
                        clearInterval(this.qrRefreshTimer);
                        clearInterval(this.qrCountdownTimer);
                        this.qrRefreshTimer = null;
                        this.qrCountdownTimer = null;
                        this.qr = null;
                    },

                    fetchQr() {
                        this.qrSecondsLeft = 20;

                        this.$axios.get("{{ route('admin.settings.whatsapp.session.qr') }}")
                            .then((response) => {
                                this.qr = response.data.qr;
                            })
                            .catch((error) => {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: this.qrErrorTemplate.replace('__ERROR__', error.response?.data?.message || ''),
                                });
                            });
                    },

                    reconnect() {
                        this.$emitter.emit('open-confirm-modal', {
                            agree: () => {
                                this.reconnecting = true;

                                this.$axios.post("{{ route('admin.settings.whatsapp.session.reconnect') }}")
                                    .then((response) => {
                                        this.reconnecting = false;

                                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                        this.checkStatus();
                                    })
                                    .catch((error) => {
                                        this.reconnecting = false;

                                        this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message });
                                    });
                            },
                        });
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
