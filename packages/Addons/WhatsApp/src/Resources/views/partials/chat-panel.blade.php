{{-- Wrapped like core's own action tiles, which each sit in a plain div. --}}
<div>
    <v-whatsapp-chat lead-id="{{ $lead->id }}"></v-whatsapp-chat>
</div>

@pushOnce('styles')
    {{--
        Real CSS, not Tailwind utilities: the Admin package's Tailwind build
        only scans its own `src/Resources/**`, so classes this addon uses and
        core doesn't are purged. The sent bubble used to be
        `bg-[var(--brand-color)] text-white` — the background was purged away
        while `text-white` survived (core uses it), leaving white text on a
        white card.
    --}}
    <style>
        /* Brand green rather than a pastel: the Correo tile already owns
           pastel green, and this is the primary channel for these leads. */
        .whatsapp-chat-tile {
            background-color: #25d366;
            color: #ffffff;
        }

        .whatsapp-chat-tile:hover {
            background-color: #1faa52;
        }

        .whatsapp-bubble-received {
            background-color: #f3f4f6;
            color: #1f2937;
        }

        .whatsapp-bubble-sent {
            background-color: var(--brand-color, #0e90d9);
            color: #ffffff;
        }

        .whatsapp-bubble-echo {
            border: 1px dashed #9ca3af;
            color: #4b5563;
        }

        /* Marks a message that carried an attachment. The file itself isn't
           downloaded into the CRM yet, so this is deliberately a label and
           not a link. */
        .whatsapp-media-chip {
            align-self: flex-start;
            border: 1px solid currentColor;
            border-radius: 9999px;
            cursor: help;
            font-size: 11px;
            font-weight: 600;
            opacity: 0.85;
            padding: 1px 8px;
        }

        .dark .whatsapp-bubble-received {
            background-color: #1f2937;
            color: #e5e7eb;
        }

        .dark .whatsapp-bubble-echo {
            color: #d1d5db;
        }
    </style>
@endPushOnce

{{--
    Must be `pushOnce('scripts')`, never a bare `once`: this partial is
    injected inside `#app`, and Vue takes the existing DOM there as its
    template on mount — script and style tags sitting in it are dropped, so
    the component below would never register. The scripts stack renders
    after `#app` closes.
--}}
@pushOnce('scripts')
    {{--
        Loaded from CDN rather than bundled into the Admin package's own Vite
        build — this addon stays self-contained without touching core's build
        pipeline, same reasoning as Zadarma's softphone widget script.

        Plain script tags, in order: the stack renders before the Vue app
        mounts and classic scripts execute in document order, so no loader
        gymnastics are needed. The previous version imported
        `laravel-echo/dist/echo.esm.js`, which **does not exist** in the
        package (its builds are echo.js / echo.common.js / echo.iife.js) —
        that 404 meant Echo never initialised and live updates silently never
        worked. The iife build declares a global `Echo` holding the *class*,
        so the instance is kept under its own name to avoid clobbering it.

        Live updates must not depend on any of this: see `startPolling()` in
        the component, which keeps the thread current even with no CDN, no
        WebSocket and no Reverb running.
    --}}
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

    <script>
        (function () {
            if (window.whatsAppEcho || ! window.Pusher || ! window.Echo) {
                return;
            }

            window.whatsAppEcho = new window.Echo({
                broadcaster: 'reverb',
                key: @json(config('broadcasting.connections.reverb.key')),
                wsHost: @json(config('broadcasting.connections.reverb.options.host')),
                wsPort: @json((int) config('broadcasting.connections.reverb.options.port', 80)),
                wssPort: @json((int) config('broadcasting.connections.reverb.options.port', 443)),
                forceTLS: @json((bool) config('broadcasting.connections.reverb.options.useTLS', true)),
                enabledTransports: ['ws', 'wss'],
            });
        })();
    </script>

    <script
        type="text/x-template"
        id="v-whatsapp-chat-template"
    >
        <div>
            <button
                type="button"
                class="whatsapp-chat-tile flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent font-medium transition-all"
                @click="$refs.chatModal.open()"
            >
                <svg
                    class="h-6 w-6"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 0 1 6.988 2.896 9.83 9.83 0 0 1 2.893 6.994c-.003 5.45-4.437 9.885-9.885 9.885M20.52 3.449C18.24 1.245 15.24 0 12.045 0 5.463 0 .104 5.359.101 11.945c0 2.096.549 4.142 1.595 5.945L0 24l6.305-1.654a11.9 11.9 0 0 0 5.683 1.448h.005c6.581 0 11.94-5.359 11.943-11.945C23.94 8.659 22.797 5.652 20.52 3.449" />
                </svg>

                @lang('whatsapp::app.chat.title')
            </button>

            {{--
                Teleported to body like every core action modal (note, mail,
                file, activity all do this). Without it the modal renders
                inside the Lead's left panel, which is `lg:sticky` — sticky
                creates a stacking context, so the modal's `z-[10003]` only
                ranks *within* that panel. The right column then paints over
                it: its activity rows use `icon-stats-up rotate-90` for the
                "old -> new" arrow, and `rotate-90` is a transform, which
                makes its own stacking context that landed above the modal.

                `position` also has no default inside v-modal (unlike `size`):
                without it both `positionClass` and the transition classes
                resolve to undefined, leaving the box absolutely positioned
                with no coordinates.
            --}}
            <Teleport to="body">
            <v-modal
                ref="chatModal"
                size="medium"
                position="center"
                @open="onOpen"
                @close="onClose"
            >
            <template v-slot:header="{ toggle }">
                <div class="flex items-center justify-between gap-2.5 border-b px-4 py-3 dark:border-gray-800">
                    <p class="text-base font-semibold text-gray-800 dark:text-white">
                        @lang('whatsapp::app.chat.title')
                    </p>

                    <span
                        class="icon-cross-large cursor-pointer text-3xl hover:rounded-md hover:bg-gray-100 dark:text-white dark:hover:bg-gray-950"
                        @click="toggle"
                    ></span>
                </div>
            </template>

            <template v-slot:content>
            <div
                ref="scrollArea"
                class="flex max-h-[60vh] min-h-[320px] flex-col gap-2 overflow-y-auto p-4"
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
                        class="flex max-w-[80%] flex-col gap-1 rounded-lg px-3 py-2 text-sm"
                        :class="bubbleClass(message)"
                    >
                        <span
                            v-if="message.media_type"
                            class="whatsapp-media-chip"
                            :title="mediaHintLabel"
                        >
                            @{{ mediaLabel(message.media_type) }}
                        </span>

                        <span v-if="message.body">@{{ message.body }}</span>
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
            </template>
            </v-modal>
            </Teleport>
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
                    pollTimer: null,
                    emptyLabel: @json(trans('whatsapp::app.chat.empty')),
                    placeholderLabel: @json(trans('whatsapp::app.chat.placeholder')),
                    sendLabel: @json(trans('whatsapp::app.chat.send-btn')),
                    typeLabels: {
                        received: @json(trans('whatsapp::app.chat.type-received')),
                        sent_api: @json(trans('whatsapp::app.chat.type-sent_api')),
                        echo: @json(trans('whatsapp::app.chat.type-echo')),
                    },
                    mediaHintLabel: @json(trans('whatsapp::app.chat.media-not-downloadable')),
                    mediaLabels: {
                        image: @json(trans('whatsapp::app.chat.media-image')),
                        video: @json(trans('whatsapp::app.chat.media-video')),
                        audio: @json(trans('whatsapp::app.chat.media-audio')),
                        document: @json(trans('whatsapp::app.chat.media-document')),
                        sticker: @json(trans('whatsapp::app.chat.media-sticker')),
                        location: @json(trans('whatsapp::app.chat.media-location')),
                        contact: @json(trans('whatsapp::app.chat.media-contact')),
                    },
                };
            },

            mounted() {
                this.load();

                this.subscribeToLiveUpdates();
            },

            beforeUnmount() {
                this.stopPolling();

                if (window.whatsAppEcho) {
                    window.whatsAppEcho.leave('whatsapp.lead.' + this.leadId);
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
                    if (! window.whatsAppEcho) {
                        return;
                    }

                    window.whatsAppEcho.private('whatsapp.lead.' + this.leadId)
                        .listen('.message.new', (message) => this.appendIfNew(message));
                },

                /**
                 * The thread stays current on its own, without depending on
                 * the CDN being reachable, the WebSocket being allowed, or
                 * Reverb running as a supervised process in production. Echo
                 * still pushes instantly when it is available — `appendIfNew`
                 * dedupes, so the two never double up.
                 *
                 * Only runs while the conversation is actually open, so a
                 * Lead sitting in a background tab costs nothing.
                 */
                startPolling() {
                    if (this.pollTimer) {
                        return;
                    }

                    this.pollTimer = setInterval(() => this.refresh(), 5000);
                },

                stopPolling() {
                    clearInterval(this.pollTimer);

                    this.pollTimer = null;
                },

                refresh() {
                    this.$axios.get("{{ url('admin/leads') }}/" + this.leadId + '/whatsapp/messages')
                        .then((response) => {
                            response.data.messages.forEach((message) => this.appendIfNew(message));
                        })
                        .catch(() => {});
                },

                bubbleClass(message) {
                    if (message.type === 'received') {
                        return 'whatsapp-bubble-received';
                    }

                    if (message.type === 'echo') {
                        return 'whatsapp-bubble-echo';
                    }

                    return 'whatsapp-bubble-sent';
                },

                mediaLabel(mediaType) {
                    return this.mediaLabels[mediaType] ?? @json(trans('whatsapp::app.chat.media-unknown'));
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

                /**
                 * The modal only renders its content while open, so the
                 * scroll area doesn't exist until now — any scroll done
                 * while it was closed was a no-op.
                 */
                onOpen() {
                    this.refresh();

                    this.startPolling();

                    this.scrollToBottom();
                },

                onClose() {
                    this.stopPolling();
                },
            },
        });
    </script>
@endPushOnce
