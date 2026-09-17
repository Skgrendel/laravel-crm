<x-admin::layouts>
    <x-slot:title>
        @lang('whatsapp::app.inbox.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-white">
                @lang('whatsapp::app.inbox.title')
            </div>
        </div>

        <v-whatsapp-inbox></v-whatsapp-inbox>
    </div>

    {{--
        Pulls in the chat styles and the `v-whatsapp-thread` component, which
        this page renders beside the list. No `$lead`, so the partial
        contributes its assets without rendering an action tile.
    --}}
    @include('whatsapp::partials.chat-panel')

    @pushOnce('styles')
        {{--
            Real CSS, not Tailwind utilities: the Admin build only scans its
            own `src/Resources`, so any class this addon uses that core does
            not is purged away. See docs/whatsapp-addon.md.
        --}}
        <style>
            /* One fixed box instead of a panel that grows with the number of
               conversations or the length of the thread: both columns scroll
               inside it, so the page itself never moves. */
            .whatsapp-inbox {
                height: calc(100vh - 190px);
                min-height: 460px;
            }

            /* The thread caps itself at 60vh for the modal on the Lead page.
               Here it has a column to fill, and that cap would leave dead
               space under the composer. */
            .whatsapp-inbox-pane > div {
                flex: 1;
                min-height: 0;
            }

            .whatsapp-inbox-pane .whatsapp-thread {
                flex: 1;
                max-height: none;
                min-height: 0;
            }

            @media (max-width: 1023px) {
                /* Stacked: a viewport-height box per column would put the
                   composer off screen. */
                .whatsapp-inbox {
                    height: auto;
                }

                .whatsapp-inbox-pane .whatsapp-thread {
                    max-height: 60vh;
                }
            }

            .whatsapp-inbox-row {
                border-bottom: 1px solid #e5e7eb;
                cursor: pointer;
            }

            .whatsapp-inbox-row:hover {
                background-color: #f9fafb;
            }

            .dark .whatsapp-inbox-row {
                border-bottom-color: #374151;
            }

            .dark .whatsapp-inbox-row:hover {
                background-color: #1f2937;
            }

            /* Waiting longest sits at the top, so the colour only has to
               separate "someone is waiting" from "handled". */
            .whatsapp-wait-badge {
                background-color: #fee2e2;
                border-radius: 9999px;
                color: #991b1b;
                font-size: 11px;
                font-weight: 600;
                padding: 2px 8px;
                white-space: nowrap;
            }

            .whatsapp-wait-badge-ok {
                background-color: #f3f4f6;
                color: #4b5563;
            }

            .whatsapp-claim-btn {
                background-color: #25d366;
                border-radius: 6px;
                color: #ffffff;
                font-size: 12px;
                font-weight: 600;
                padding: 4px 10px;
                white-space: nowrap;
            }

            .whatsapp-claim-btn:hover {
                background-color: #1faa52;
            }

            .whatsapp-claim-btn:disabled {
                opacity: 0.6;
            }

            .whatsapp-unread-dot {
                background-color: #25d366;
                border-radius: 9999px;
                display: inline-block;
                height: 8px;
                width: 8px;
            }

            .whatsapp-inbox-tab {
                border-bottom: 2px solid transparent;
                cursor: pointer;
                font-size: 14px;
                padding: 8px 4px;
            }

            .whatsapp-inbox-tab-active {
                border-bottom-color: #25d366;
                font-weight: 600;
            }

            .whatsapp-inbox-row-active {
                background-color: #f0fdf4;
                border-left: 3px solid #25d366;
            }

            .dark .whatsapp-inbox-row-active {
                background-color: #14281d;
            }
        </style>
    @endPushOnce

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-whatsapp-inbox-template"
        >
            <div class="whatsapp-inbox flex gap-4 max-lg:flex-col">
            <div class="flex w-full min-w-0 flex-col overflow-hidden rounded-lg border border-gray-300 bg-white lg:max-w-[380px] dark:border-gray-800 dark:bg-gray-900">
                <div class="flex shrink-0 items-center gap-5 border-b border-gray-300 px-4 dark:border-gray-800">
                    <span
                        v-for="tab in tabs"
                        :key="tab.value"
                        class="whatsapp-inbox-tab dark:text-white"
                        :class="{ 'whatsapp-inbox-tab-active': scope === tab.value }"
                        @click="changeScope(tab.value)"
                    >
                        @{{ tab.label }}
                    </span>
                </div>

                {{-- Scrolls on its own so the list length never changes the
                     height of the page. --}}
                <div class="flex-1 overflow-y-auto">
                <p
                    v-if="! loading && conversations.length === 0"
                    class="p-8 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    @{{ emptyLabel }}
                </p>

                <div
                    v-for="conversation in conversations"
                    :key="conversation.id"
                    class="whatsapp-inbox-row flex items-center gap-4 px-4 py-3"
                    {{-- `selectedLeadId &&` matters: without it every
                         unassigned row (lead_id null) matches a null
                         selection and the whole list renders as selected. --}}
                    :class="{ 'whatsapp-inbox-row-active': selectedLeadId && conversation.lead_id === selectedLeadId }"
                    @click="open(conversation)"
                >
                    <span
                        class="whatsapp-unread-dot"
                        :style="{ visibility: conversation.is_unread ? 'visible' : 'hidden' }"
                    ></span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-gray-800 dark:text-white">
                            @{{ conversation.name }}
                        </p>

                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            @{{ conversation.is_hidden_number ? hiddenNumberLabel : conversation.phone }}
                            <template v-if="conversation.owner"> · @{{ conversation.owner }}</template>
                            <template v-else> · @{{ unassignedLabel }}</template>
                        </p>
                    </div>

                    {{-- Without a Lead there is nothing to open, so the row
                         offers the one action that makes it openable. --}}
                    <button
                        v-if="! conversation.lead_id"
                        type="button"
                        class="whatsapp-claim-btn"
                        :disabled="claiming === conversation.id"
                        @click.stop="claim(conversation)"
                    >
                        @{{ claiming === conversation.id ? claimingLabel : claimLabel }}
                    </button>

                    <span
                        v-else
                        class="whatsapp-wait-badge"
                        :class="{ 'whatsapp-wait-badge-ok': ! conversation.is_waiting }"
                    >
                        @{{ waitLabel(conversation) }}
                    </span>
                </div>
                </div>
            </div>

            <div class="whatsapp-inbox-pane flex w-full min-w-0 flex-col overflow-hidden rounded-lg border border-gray-300 bg-white dark:border-gray-800 dark:bg-gray-900">
                {{--
                    The conversation opens right here, not on the Lead page:
                    this screen is where an agent works, so sending them
                    somewhere else to reply defeats the point of the list.
                    `:key` forces a fresh thread per conversation instead of
                    reusing one with the previous Lead's messages in it.
                --}}
                <v-whatsapp-thread
                    v-if="selectedLeadId"
                    :key="selectedLeadId"
                    :lead-id="selectedLeadId"
                ></v-whatsapp-thread>

                <p
                    v-else
                    class="p-8 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    @{{ pickOneLabel }}
                </p>
            </div>
            </div>
        </script>

        <script type="module">
            app.component('v-whatsapp-inbox', {
                template: '#v-whatsapp-inbox-template',

                data() {
                    return {
                        conversations: [],
                        loading: true,
                        scope: 'mine',
                        selectedLeadId: null,
                        claiming: null,
                        pollTimer: null,
                        claimLabel: @json(trans('whatsapp::app.inbox.claim')),
                        claimingLabel: @json(trans('whatsapp::app.inbox.claiming')),
                        emptyLabel: @json(trans('whatsapp::app.inbox.empty')),
                        pickOneLabel: @json(trans('whatsapp::app.inbox.pick-one')),
                        hiddenNumberLabel: @json(trans('whatsapp::app.chat.hidden-number')),
                        unassignedLabel: @json(trans('whatsapp::app.inbox.unassigned')),
                        handledLabel: @json(trans('whatsapp::app.inbox.handled')),
                        tabs: [
                            { value: 'mine', label: @json(trans('whatsapp::app.inbox.scope-mine')) },
                            { value: 'unassigned', label: @json(trans('whatsapp::app.inbox.scope-unassigned')) },
                            { value: 'all', label: @json(trans('whatsapp::app.inbox.scope-all')) },
                        ],
                    };
                },

                mounted() {
                    this.load();

                    /**
                     * Same reasoning as the chat panel: polling rather than
                     * a WebSocket, so the inbox stays current without
                     * depending on Reverb running. Slower cadence — this is
                     * a list, not a conversation.
                     */
                    this.pollTimer = setInterval(() => this.load(), 15000);
                },

                beforeUnmount() {
                    clearInterval(this.pollTimer);
                },

                methods: {
                    load() {
                        this.$axios.get("{{ route('admin.whatsapp.inbox.list') }}", { params: { scope: this.scope } })
                            .then((response) => {
                                this.conversations = response.data.conversations;
                                this.loading = false;
                            })
                            .catch(() => {
                                this.loading = false;
                            });
                    },

                    changeScope(scope) {
                        this.scope = scope;
                        this.loading = true;
                        this.load();
                    },

                    /**
                     * Claiming creates the Lead and hands it to this agent,
                     * then opens the thread — taking a conversation and
                     * having to hunt for it again would be a pointless extra
                     * step.
                     */
                    claim(conversation) {
                        this.claiming = conversation.id;

                        this.$axios.post("{{ url('admin/whatsapp/inbox') }}/" + conversation.id + '/claim')
                            .then((response) => {
                                this.claiming = null;
                                conversation.lead_id = response.data.lead_id;

                                this.open(conversation);
                                this.load();
                            })
                            .catch((error) => {
                                this.claiming = null;

                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error.response?.data?.message ?? error.message,
                                });
                            });
                    },

                    open(conversation) {
                        if (! conversation.lead_id) {
                            return;
                        }

                        this.selectedLeadId = conversation.lead_id;

                        // Clear the marker straight away rather than waiting
                        // for the next poll, so the row stops shouting the
                        // moment it is read.
                        conversation.is_unread = false;

                        this.$axios.post("{{ url('admin/whatsapp/inbox') }}/" + conversation.id + '/read')
                            .catch(() => {});
                    },

                    waitLabel(conversation) {
                        if (! conversation.is_waiting) {
                            return this.handledLabel;
                        }

                        const minutes = Math.max(
                            0,
                            Math.floor((Date.now() - new Date(conversation.waiting_since)) / 60000)
                        );

                        if (minutes < 60) {
                            return minutes + ' min';
                        }

                        const hours = Math.floor(minutes / 60);

                        return hours < 24 ? hours + ' h' : Math.floor(hours / 24) + ' d';
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
