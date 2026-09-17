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

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('zadarma::app.settings.index.webhook-secret')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="password"
                        id="webhook_secret"
                        name="webhook_secret"
                        :label="trans('zadarma::app.settings.index.webhook-secret')"
                        placeholder="{{ $settings->webhook_secret ? '••••••••' : '' }}"
                    />

                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        @lang('zadarma::app.settings.index.webhook-secret-info')
                    </p>
                </x-admin::form.control-group>

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
    @endPushOnce
</x-admin::layouts>
