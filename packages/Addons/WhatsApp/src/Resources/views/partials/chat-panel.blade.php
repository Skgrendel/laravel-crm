<div class="rounded-lg border border-gray-300 bg-white dark:border-gray-800 dark:bg-gray-900">
    <v-whatsapp-chat lead-id="{{ $lead->id }}"></v-whatsapp-chat>
</div>

@once
    <script>
        /**
         * Loaded from CDN rather than bundled into the Admin package's own
         * Vite build — this addon stays self-contained without touching
         * core's build pipeline, same reasoning as Zadarma's softphone
         * widget script. window.Echo is a single instance shared by any
         * number of `v-whatsapp-chat` components on the page.
         */
        (function () {
            if (window.Echo) {
                return;
            }

            function initEcho() {
                window.Echo = new window.Echo_.default({
                    broadcaster: 'reverb',
                    key: @json(config('broadcasting.connections.reverb.key')),
                    wsHost: @json(config('broadcasting.connections.reverb.options.host')),
                    wsPort: @json((int) config('broadcasting.connections.reverb.options.port', 80)),
                    wssPort: @json((int) config('broadcasting.connections.reverb.options.port', 443)),
                    forceTLS: @json((bool) config('broadcasting.connections.reverb.options.useTLS', true)),
                    enabledTransports: ['ws', 'wss'],
                });
            }

            var pusherScript = document.createElement('script');
            pusherScript.src = 'https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js';
            pusherScript.onload = function () {
                var echoScript = document.createElement('script');
                echoScript.type = 'module';
                echoScript.innerHTML = 'import Echo from "https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.esm.js"; window.Echo_ = { default: Echo }; window.dispatchEvent(new Event("whatsapp-echo-lib-ready"));';
                document.head.appendChild(echoScript);
            };
            document.head.appendChild(pusherScript);

            window.addEventListener('whatsapp-echo-lib-ready', initEcho, { once: true });
        })();
    </script>

    <script
        type="text/x-template"
        id="v-whatsapp-chat-template"
    >
        <div class="flex flex-col">
            <div class="flex items-center justify-between border-b border-gray-300 p-4 dark:border-gray-800">
                <p class="text-base font-semibold text-gray-800 dark:text-white">
                    @lang('whatsapp::app.chat.title')
                </p>
            </div>

            <div
                ref="scrollArea"
                class="flex max-h-[420px] min-h-[200px] flex-col gap-2 overflow-y-auto p-4"
            >
                <p
                    v-if="! loading && messages.length === 0"
                    class="text-center text-xs text-gray-500 dark:text-gray-400"
                >
                    @{{ emptyLabel }}
                </p>

                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="flex flex-col"
                    :class="message.type === 'received' ? 'items-start' : 'items-end'"
                >
                    <div
                        class="max-w-[80%] rounded-lg px-3 py-2 text-sm"
                        :class="bubbleClass(message)"
                    >
                        @{{ message.body }}
                    </div>

                    <span class="mt-0.5 text-[10px] text-gray-400">
                        @{{ typeLabels[message.type] }} · @{{ formatTime(message.sent_at) }}
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-2.5 border-t border-gray-300 p-4 dark:border-gray-800">
                <input
                    type="text"
                    v-model="draft"
                    @keyup.enter="send"
                    :placeholder="placeholderLabel"
                    class="control h-10 flex-1 rounded-md border px-3 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                />

                <button
                    type="button"
                    class="primary-button"
                    :disabled="sending || ! draft.trim()"
                    @click="send"
                >
                    @{{ sendLabel }}
                </button>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-whatsapp-chat', {
            template: '#v-whatsapp-chat-template',

            props: {
                leadId: [String, Number],
            },

            data() {
                return {
                    messages: [],
                    draft: '',
                    loading: true,
                    sending: false,
                    emptyLabel: @json(trans('whatsapp::app.chat.empty')),
                    placeholderLabel: @json(trans('whatsapp::app.chat.placeholder')),
                    sendLabel: @json(trans('whatsapp::app.chat.send-btn')),
                    typeLabels: {
                        received: @json(trans('whatsapp::app.chat.type-received')),
                        sent_api: @json(trans('whatsapp::app.chat.type-sent_api')),
                        echo: @json(trans('whatsapp::app.chat.type-echo')),
                    },
                };
            },

            mounted() {
                this.load();

                if (window.Echo) {
                    this.subscribeToLiveUpdates();
                } else {
                    // CDN script (see the @once block above) loads
                    // asynchronously and usually isn't ready yet by the
                    // time this component mounts.
                    window.addEventListener('whatsapp-echo-lib-ready', () => this.subscribeToLiveUpdates(), { once: true });
                }
            },

            beforeUnmount() {
                if (window.Echo) {
                    window.Echo.leave('whatsapp.lead.' + this.leadId);
                }
            },

            methods: {
                load() {
                    this.$axios.get("{{ url('admin/leads') }}/" + this.leadId + '/whatsapp/messages')
                        .then((response) => {
                            this.messages = response.data.messages;
                            this.loading = false;
                            this.scrollToBottom();
                        })
                        .catch(() => {
                            this.loading = false;
                        });
                },

                send() {
                    const message = this.draft.trim();

                    if (! message) {
                        return;
                    }

                    this.sending = true;

                    this.$axios.post("{{ url('admin/leads') }}/" + this.leadId + '/whatsapp/messages', { message })
                        .then((response) => {
                            this.sending = false;
                            this.draft = '';
                            this.appendIfNew(response.data.message);
                        })
                        .catch((error) => {
                            this.sending = false;

                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message ?? error.message,
                            });
                        });
                },

                /**
                 * Live updates arrive over the same private channel regardless
                 * of who triggered them (this tab's own send, another tab open
                 * on the same Lead, or a real customer reply) — de-duped here
                 * so this tab's optimistic `send()` append doesn't double up
                 * once the broadcast for that same message arrives back.
                 */
                appendIfNew(message) {
                    if (this.messages.some((existing) => existing.id === message.id)) {
                        return;
                    }

                    this.messages.push(message);
                    this.scrollToBottom();
                },

                subscribeToLiveUpdates() {
                    if (! window.Echo) {
                        return;
                    }

                    window.Echo.private('whatsapp.lead.' + this.leadId)
                        .listen('.message.new', (message) => this.appendIfNew(message));
                },

                bubbleClass(message) {
                    if (message.type === 'received') {
                        return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
                    }

                    if (message.type === 'echo') {
                        return 'border border-dashed border-gray-400 text-gray-600 dark:text-gray-300';
                    }

                    return 'bg-[var(--brand-color,#0E90D9)] text-white';
                },

                formatTime(value) {
                    return new Date(value).toLocaleString();
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        const el = this.$refs.scrollArea;

                        if (el) {
                            el.scrollTop = el.scrollHeight;
                        }
                    });
                },
            },
        });
    </script>
@endonce
