<x-admin::layouts>
    <x-slot:title>
        @lang('zadarma::app.settings.index.title')
    </x-slot>

    <x-admin::form
        :action="route('admin.settings.zadarma.update')"
        method="PUT"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="settings.zadarma" />

                    <div class="text-xl font-bold dark:text-white">
                        @lang('zadarma::app.settings.index.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <x-admin::button
                        button-type="submit"
                        class="primary-button"
                        :title="trans('zadarma::app.settings.index.save-btn')"
                    />
                </div>
            </div>

            <!-- Enabled toggle card -->
            <div class="flex items-center justify-between rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-2.5">
                    <span class="icon-call text-2xl dark:text-white"></span>

                    <span class="text-base font-semibold text-gray-800 dark:text-white">
                        Zadarma VoIP
                    </span>
                </div>

                <x-admin::form.control-group class="!mb-0 flex items-center gap-4">
                    <x-admin::form.control-group.label class="!mb-0">
                        @lang('zadarma::app.settings.index.enabled')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="switch"
                        class="cursor-pointer"
                        name="enabled"
                        id="enabled"
                        value="1"
                        for="enabled"
                        :checked="(boolean) $settings->enabled"
                        :label="trans('zadarma::app.settings.index.enabled')"
                    />
                </x-admin::form.control-group>
            </div>

            <!-- Credentials card -->
            <v-zadarma-credentials></v-zadarma-credentials>

            <!-- Webhook URL card -->
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('zadarma::app.settings.index.webhook-url-title')
                </p>

                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    @lang('zadarma::app.settings.index.webhook-url-info')
                </p>

                <v-zadarma-webhook-url
                    url="{{ route('admin.zadarma.webhook', $settings->webhook_secret) }}"
                ></v-zadarma-webhook-url>
            </div>

            <!-- Extension mapping card -->
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('zadarma::app.settings.index.extensions-title')
                </p>

                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    @lang('zadarma::app.settings.index.extensions-info')
                </p>

                <v-zadarma-extensions
                    :agents="{{ $users->map(fn ($user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'extension' => optional($extensionMappings->get($user->id))->extension,
                    ])->values()->toJson() }}"
                ></v-zadarma-extensions>
            </div>

            <!-- Call forwarding card -->
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('zadarma::app.settings.index.redirection-title')
                </p>

                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    @lang('zadarma::app.settings.index.redirection-info')
                </p>

                <v-zadarma-redirection
                    :agents="{{ $users->map(fn ($user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'extension' => optional($extensionMappings->get($user->id))->extension,
                    ])->filter(fn ($agent) => ! empty($agent['extension']))->values()->toJson() }}"
                ></v-zadarma-redirection>
            </div>

            <!-- DID numbers card -->
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('zadarma::app.settings.index.direct-numbers-title')
                </p>

                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    @lang('zadarma::app.settings.index.direct-numbers-info')
                </p>

                <v-zadarma-direct-numbers></v-zadarma-direct-numbers>
            </div>
        </div>
    </x-admin::form>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-zadarma-credentials-template"
        >
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-base font-semibold text-gray-800 dark:text-white">
                        @lang('zadarma::app.settings.index.credentials-title')
                    </p>

                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="testStatus === 'success'
                            ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'"
                    >
                        @{{ testStatus === 'success' ? connectedLabel : disconnectedLabel }}
                    </span>
                </div>

                <div class="flex gap-4 max-sm:flex-col">
                    <x-admin::form.control-group class="flex-1">
                        <x-admin::form.control-group.label>
                            @lang('zadarma::app.settings.index.api-key')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="password"
                            id="api_key"
                            name="api_key"
                            v-model="apiKey"
                            :label="trans('zadarma::app.settings.index.api-key')"
                            placeholder="{{ $settings->api_key ? '••••••••' : '' }}"
                        />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group class="flex-1">
                        <x-admin::form.control-group.label>
                            @lang('zadarma::app.settings.index.api-secret')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="password"
                            id="api_secret"
                            name="api_secret"
                            v-model="apiSecret"
                            :label="trans('zadarma::app.settings.index.api-secret')"
                            placeholder="{{ $settings->api_secret ? '••••••••' : '' }}"
                        />
                    </x-admin::form.control-group>
                </div>

                <div class="mt-2 flex items-center gap-2.5">
                    <!--
                        Plain native button on purpose: x-admin::button always
                        renders a <button> with no explicit `type`, which the
                        browser defaults to "submit" — inside this form that
                        would also submit the settings form on click.
                    -->
                    <button
                        type="button"
                        class="secondary-button"
                        :disabled="isTesting"
                        @click="testConnection"
                    >
                        @{{ isTesting ? testingLabel : testLabel }}
                    </button>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-zadarma-credentials', {
                template: '#v-zadarma-credentials-template',

                data() {
                    return {
                        apiKey: '',
                        apiSecret: '',
                        isTesting: false,
                        testStatus: 'idle',
                        connectedLabel: @json(trans('zadarma::app.settings.index.connected')),
                        disconnectedLabel: @json(trans('zadarma::app.settings.index.disconnected')),
                        testLabel: @json(trans('zadarma::app.settings.index.test-connection-btn')),
                        testingLabel: @json(trans('zadarma::app.settings.index.test-connection-btn')).concat('...'),
                    };
                },

                methods: {
                    testConnection() {
                        this.isTesting = true;

                        this.$axios.post("{{ route('admin.settings.zadarma.test_connection') }}", {
                            api_key: this.apiKey,
                            api_secret: this.apiSecret,
                        })
                            .then((response) => {
                                this.isTesting = false;
                                this.testStatus = 'success';

                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            })
                            .catch((error) => {
                                this.isTesting = false;
                                this.testStatus = 'error';

                                this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                            });
                    },
                },
            });
        </script>

        <script
            type="text/x-template"
            id="v-zadarma-webhook-url-template"
        >
            <div class="flex items-center gap-2.5 max-sm:flex-col max-sm:items-stretch">
                <input
                    type="text"
                    class="control h-11 flex-1 rounded-md border px-3 py-2.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                    :value="url"
                    readonly
                    @click="$event.target.select()"
                />

                <button
                    type="button"
                    class="secondary-button"
                    @click="copyUrl"
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
            app.component('v-zadarma-webhook-url', {
                template: '#v-zadarma-webhook-url-template',

                props: {
                    url: String,
                },

                data() {
                    return {
                        isRegenerating: false,
                        copyLabel: @json(trans('zadarma::app.settings.index.copy-btn')),
                        regenerateLabel: @json(trans('zadarma::app.settings.index.regenerate-btn')),
                    };
                },

                methods: {
                    copyUrl() {
                        navigator.clipboard.writeText(this.url);

                        this.$emitter.emit('add-flash', { type: 'success', message: @json(trans('zadarma::app.settings.index.copy-success')) });
                    },

                    regenerate() {
                        this.$emitter.emit('open-confirm-modal', {
                            agree: () => {
                                this.isRegenerating = true;

                                this.$axios.post("{{ route('admin.settings.zadarma.webhook_secret.regenerate') }}")
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
            id="v-zadarma-extensions-template"
        >
            <div class="flex flex-col gap-2.5">
                <div
                    v-for="agent in agents"
                    :key="agent.id"
                    class="flex items-center gap-4"
                >
                    <x-admin::avatar ::name="agent.name" />

                    <span class="flex-1 text-sm text-gray-800 dark:text-white">
                        @{{ agent.name }}
                    </span>

                    <input
                        type="text"
                        class="control h-9 w-32 rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                        v-model="agent.extension"
                        placeholder="101"
                        maxlength="20"
                    />
                </div>

                <div class="mt-2 flex items-center gap-2.5">
                    <button
                        type="button"
                        class="secondary-button"
                        :disabled="isSaving"
                        @click="save"
                    >
                        @{{ isSaving ? savingLabel : saveMappingLabel }}
                    </button>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-zadarma-extensions', {
                template: '#v-zadarma-extensions-template',

                props: {
                    agents: Array,
                },

                data() {
                    return {
                        isSaving: false,
                        saveMappingLabel: @json(trans('zadarma::app.settings.index.save-mapping-btn')),
                        savingLabel: @json(trans('zadarma::app.settings.index.save-mapping-btn')).concat('...'),
                    };
                },

                methods: {
                    save() {
                        this.isSaving = true;

                        const mappings = this.agents.map((agent) => ({
                            user_id: agent.id,
                            extension: agent.extension,
                        }));

                        this.$axios.put("{{ route('admin.settings.zadarma.extensions.update') }}", { mappings })
                            .then((response) => {
                                this.isSaving = false;

                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            })
                            .catch((error) => {
                                this.isSaving = false;

                                this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                            });
                    },
                },
            });
        </script>

        <script
            type="text/x-template"
            id="v-zadarma-redirection-template"
        >
            <div class="flex flex-col gap-2.5">
                <div
                    v-for="agent in agents"
                    :key="agent.id"
                    class="rounded-md border border-gray-200 p-3 dark:border-gray-800"
                >
                    <div class="flex items-center gap-4">
                        <x-admin::avatar ::name="agent.name" />

                        <span class="flex-1 text-sm text-gray-800 dark:text-white">
                            @{{ agent.name }} <span class="text-gray-400">(@{{ agent.extension }})</span>
                        </span>

                        <button
                            type="button"
                            class="secondary-button"
                            :disabled="agent._loading"
                            @click="load(agent)"
                        >
                            @{{ agent._loading ? loadingLabel : loadLabel }}
                        </button>
                    </div>

                    <div
                        v-if="agent._loaded"
                        class="mt-3 flex flex-wrap items-center gap-2.5"
                    >
                        <select
                            v-model="agent._type"
                            class="control h-9 rounded-md border px-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <option value="off">@{{ offLabel }}</option>
                            <option value="phone">@{{ phoneLabel }}</option>
                            <option value="voicemail">@{{ voicemailLabel }}</option>
                        </select>

                        <select
                            v-if="agent._type !== 'off'"
                            v-model="agent._condition"
                            class="control h-9 rounded-md border px-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <option value="noanswer">@{{ noAnswerLabel }}</option>
                            <option value="always">@{{ alwaysLabel }}</option>
                        </select>

                        <input
                            v-if="agent._type !== 'off'"
                            type="text"
                            v-model="agent._destination"
                            :placeholder="agent._type === 'voicemail' ? destinationEmailLabel : destinationPhoneLabel"
                            class="control h-9 flex-1 rounded-md border px-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                        />

                        <button
                            type="button"
                            class="primary-button"
                            :disabled="agent._saving"
                            @click="save(agent)"
                        >
                            @{{ agent._saving ? savingLabel : saveLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-zadarma-redirection', {
                template: '#v-zadarma-redirection-template',

                props: {
                    agents: Array,
                },

                data() {
                    return {
                        loadLabel: @json(trans('zadarma::app.settings.index.redirection-load-btn')),
                        loadingLabel: '...',
                        offLabel: @json(trans('zadarma::app.settings.index.redirection-off')),
                        phoneLabel: @json(trans('zadarma::app.settings.index.redirection-phone')),
                        voicemailLabel: @json(trans('zadarma::app.settings.index.redirection-voicemail')),
                        alwaysLabel: @json(trans('zadarma::app.settings.index.redirection-condition-always')),
                        noAnswerLabel: @json(trans('zadarma::app.settings.index.redirection-condition-noanswer')),
                        destinationPhoneLabel: @json(trans('zadarma::app.settings.index.redirection-destination-phone')),
                        destinationEmailLabel: @json(trans('zadarma::app.settings.index.redirection-destination-email')),
                        saveLabel: @json(trans('zadarma::app.settings.index.redirection-save-btn')),
                        savingLabel: '...',
                    };
                },

                methods: {
                    load(agent) {
                        agent._loading = true;

                        this.$axios.get("{{ url('admin/settings/zadarma/pbx/redirection') }}/" + encodeURIComponent(agent.extension))
                            .then((response) => {
                                agent._loading = false;
                                agent._loaded = true;
                                agent._type = response.data.current_status === 'on' ? (response.data.type || 'phone') : 'off';
                                agent._condition = response.data.condition || 'noanswer';
                                agent._destination = response.data.destination || '';
                            })
                            .catch((error) => {
                                agent._loading = false;

                                this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                            });
                    },

                    save(agent) {
                        agent._saving = true;

                        this.$axios.put("{{ url('admin/settings/zadarma/pbx/redirection') }}/" + encodeURIComponent(agent.extension), {
                            type: agent._type,
                            condition: agent._condition,
                            destination: agent._destination,
                        })
                            .then(() => {
                                agent._saving = false;

                                this.$emitter.emit('add-flash', { type: 'success', message: @json(trans('zadarma::app.settings.index.redirection-save-success')) });
                            })
                            .catch((error) => {
                                agent._saving = false;

                                this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                            });
                    },
                },
            });
        </script>

        <script
            type="text/x-template"
            id="v-zadarma-direct-numbers-template"
        >
            <div>
                <button
                    v-if="! loaded"
                    type="button"
                    class="secondary-button"
                    :disabled="loading"
                    @click="load"
                >
                    @{{ loading ? '...' : loadLabel }}
                </button>

                <div
                    v-else-if="numbers.length === 0"
                    class="text-xs text-gray-500 dark:text-gray-400"
                >
                    @{{ emptyLabel }}
                </div>

                <table
                    v-else
                    class="w-full text-left text-sm"
                >
                    <thead>
                        <tr class="text-xs text-gray-500 dark:text-gray-400">
                            <th class="pb-2">Number</th>
                            <th class="pb-2">Description</th>
                            <th class="pb-2">SIP</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="number in numbers"
                            :key="number.number"
                            class="border-t border-gray-100 dark:border-gray-800"
                        >
                            <td class="py-1.5 text-gray-800 dark:text-white">@{{ number.number }}</td>
                            <td class="py-1.5 text-gray-500 dark:text-gray-400">@{{ number.description }}</td>
                            <td class="py-1.5 text-gray-500 dark:text-gray-400">@{{ number.sip_name || number.sip }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </script>

        <script type="module">
            app.component('v-zadarma-direct-numbers', {
                template: '#v-zadarma-direct-numbers-template',

                data() {
                    return {
                        loaded: false,
                        loading: false,
                        numbers: [],
                        loadLabel: @json(trans('zadarma::app.settings.index.direct-numbers-load-btn')),
                        emptyLabel: @json(trans('zadarma::app.settings.index.direct-numbers-empty')),
                    };
                },

                methods: {
                    load() {
                        this.loading = true;

                        this.$axios.get("{{ route('admin.settings.zadarma.pbx.direct_numbers') }}")
                            .then((response) => {
                                this.loading = false;
                                this.loaded = true;
                                this.numbers = response.data.numbers;
                            })
                            .catch((error) => {
                                this.loading = false;

                                this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                            });
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
