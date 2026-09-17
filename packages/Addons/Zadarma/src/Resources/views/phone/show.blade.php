<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@lang('zadarma::app.phone.page-title')</title>

    <style>
        /**
         * Zadarma's own widget is a fixed-size 348px-wide box (see their
         * style.min.css), meant to float over an existing page — not to
         * fill a dedicated window. A dark full-height background behind it
         * just reads as empty dead space, so this stays light/neutral and
         * lets the widget sit near the top instead of being stretched.
         */
        html, body {
            height: 100%;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, Arial, sans-serif;
            background: #f3f4f6;
            color: #374151;
        }

        #zadarma-phone-status {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            padding: 24px;
            text-align: center;
            font-size: 14px;
            line-height: 1.5;
        }

        /**
         * Positioned to sit immediately to the right of the widget, which
         * Zadarma renders as a fixed 348px-wide box at left:24px/top:24px
         * (see the `position` argument passed to zadarmaWidgetFn below).
         */
        #zadarma-recent-calls {
            position: fixed;
            top: 24px;
            left: 396px;
            width: 280px;
            max-height: 500px;
            overflow-y: auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 2px rgba(0,0,0,.24), 0 1px 7px rgba(0,0,0,.14);
        }

        #zadarma-recent-calls h2 {
            margin: 0;
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 600;
            border-bottom: 1px solid #e5e7eb;
        }

        .zadarma-recent-call {
            display: block;
            padding: 10px 16px;
            border-bottom: 1px solid #f3f4f6;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }

        .zadarma-recent-call:hover {
            background: #f9fafb;
        }

        .zadarma-recent-call .number {
            font-size: 13px;
            font-weight: 600;
            color: #111827;
        }

        .zadarma-recent-call .meta {
            margin-top: 2px;
            font-size: 11px;
            color: #6b7280;
        }

        .zadarma-recent-call .direction-incoming {
            color: #16a34a;
        }

        .zadarma-recent-call .direction-outgoing {
            color: #2563eb;
        }

        #zadarma-recent-calls .empty {
            padding: 16px;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
@php
    $zadarmaWidgetLocales = ['ru', 'en', 'es', 'fr', 'de', 'pl', 'ua'];
    $zadarmaWidgetLocale = in_array(app()->getLocale(), $zadarmaWidgetLocales) ? app()->getLocale() : 'en';
@endphp
<body>
    <script>
        /**
         * Global (not inside the IIFE below) so the inline onclick handlers
         * on recent-call links can reach it. Navigates the CRM tab that
         * opened this popup to the Lead, instead of the popup itself.
         */
        function zadarmaOpenLead(event, anchor) {
            event.preventDefault();

            const url = anchor.getAttribute('data-lead-url');

            if (window.opener && ! window.opener.closed) {
                window.opener.location.href = url;
                window.opener.focus();
            } else {
                window.open(url, '_blank');
            }

            return false;
        }
    </script>
    @if (empty($extension))
        <div id="zadarma-phone-status">{{ trans('zadarma::app.phone.no-extension') }}</div>
    @else
        <div id="zadarma-phone-status">@lang('zadarma::app.phone.loading')</div>

        <div id="zadarma-recent-calls">
            <h2>@lang('zadarma::app.phone.recent-calls-title')</h2>

            @forelse ($recentCalls as $call)
                @php
                    $number = $call->direction === 'incoming' ? $call->caller_id : $call->called_number;
                @endphp

                @if ($call->lead_id)
                    <a
                        href="{{ route('admin.leads.edit', $call->lead_id) }}"
                        class="zadarma-recent-call"
                        data-lead-url="{{ route('admin.leads.edit', $call->lead_id) }}"
                        onclick="return zadarmaOpenLead(event, this);"
                    >
                @else
                    <div class="zadarma-recent-call">
                @endif

                    <div class="number">{{ $number ?: '—' }}</div>
                    <div class="meta">
                        <span class="{{ $call->direction === 'incoming' ? 'direction-incoming' : 'direction-outgoing' }}">
                            {{ $call->direction === 'incoming' ? trans('zadarma::app.phone.incoming') : trans('zadarma::app.phone.outgoing') }}
                        </span>
                        · {{ $call->duration ?? 0 }}s · {{ $call->created_at->format('d/m H:i') }}
                        @unless ($call->lead_id)
                            · {{ trans('zadarma::app.phone.no-lead-match') }}
                        @endunless
                    </div>

                @if ($call->lead_id)
                    </a>
                @else
                    </div>
                @endif
            @empty
                <div class="empty">@lang('zadarma::app.phone.no-recent-calls')</div>
            @endforelse
        </div>
    @endif

    @unless (empty($extension))
    <script>
        (function () {
            const statusEl = document.getElementById('zadarma-phone-status');

            const channel = ('BroadcastChannel' in window) ? new BroadcastChannel('zadarma-softphone') : null;

            function broadcast(status, extra) {
                if (channel) {
                    channel.postMessage(Object.assign({ status: status }, extra || {}));
                }
            }

            function showMessage(message) {
                statusEl.textContent = message;
                statusEl.style.display = 'flex';
            }

            /**
             * The widget's own footprint depends on its internal state
             * (numpad open/closed, locale text length, etc.), so instead of
             * guessing a fixed popup size, measure the actual rendered
             * widget once it settles and resize/center this window snugly
             * around it — reliable regardless of Zadarma's exact layout.
             */
            function fitWindowToWidget() {
                setTimeout(function () {
                    const widgetEl = document.getElementById('zdrmWPhI');

                    if (! widgetEl) {
                        return;
                    }

                    const recentEl = document.getElementById('zadarma-recent-calls');
                    const margin = 24;

                    const widgetRect = widgetEl.getBoundingClientRect();

                    // Union with the recent-calls panel, when present, so the window fits both side by side.
                    const rect = recentEl
                        ? {
                            width: Math.max(widgetRect.right, recentEl.getBoundingClientRect().right),
                            height: Math.max(widgetRect.height, recentEl.getBoundingClientRect().height) + margin,
                        }
                        : { width: widgetRect.width + margin, height: widgetRect.height };

                    const targetInnerWidth = Math.ceil(rect.width) + margin;
                    const targetInnerHeight = Math.ceil(rect.height) + margin;

                    const chromeWidth = window.outerWidth - window.innerWidth;
                    const chromeHeight = window.outerHeight - window.innerHeight;

                    try {
                        window.resizeTo(targetInnerWidth + chromeWidth, targetInnerHeight + chromeHeight);

                        widgetEl.style.left = margin + 'px';
                        widgetEl.style.top = margin + 'px';

                        const screenWidth = window.screen.availWidth;
                        const screenHeight = window.screen.availHeight;

                        window.moveTo(
                            Math.round((screenWidth - window.outerWidth) / 2),
                            Math.round((screenHeight - window.outerHeight) / 2)
                        );
                    } catch (e) {
                        // Some browsers restrict resizeTo/moveTo; the widget still works, just not auto-centered.
                    }
                }, 400);
            }

            /**
             * If opened from a Lead's "Llamar" button before the widget
             * was ready (see the click-to-call partial), the number to
             * dial travels as a query param instead of a direct
             * `window.zdrmWebPhone.call()` cross-window call.
             */
            function autoDialFromQueryString() {
                const number = new URLSearchParams(window.location.search).get('dial');

                if (! number) {
                    return;
                }

                // window.zdrmWebPhone exists once widget-api.min.js has run, but
                // registration with Zadarma's servers still needs a moment.
                setTimeout(function () {
                    if (window.zdrmWebPhone && typeof window.zdrmWebPhone.call === 'function') {
                        window.zdrmWebPhone.call(number);
                    }
                }, 1500);
            }

            /**
             * Zadarma's widget has no documented public event for incoming
             * calls (confirmed by reading their source, not just docs), so
             * this polls the one thing that IS reliably readable: the
             * widget's own phone-number input, which it fills in and flags
             * with an "incoming" class as soon as a call rings in.
             */
            function startIncomingCallWatcher() {
                let lastSeenCallId = null;

                setInterval(function () {
                    if (! window.zdrmWebPhone || window.zdrmWebPhone.callState !== 'incoming') {
                        return;
                    }

                    const input = document.getElementById('zdrm-webphone-phonenumber-input');
                    const number = input ? input.value : null;

                    if (! number || number === lastSeenCallId) {
                        return;
                    }

                    lastSeenCallId = number;

                    broadcast('incoming', { number: number });

                    fetch(@json(route('admin.zadarma.phone.lookup_lead')) + '?number=' + encodeURIComponent(number), {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (data.found) {
                                broadcast('incoming-lead-match', { url: data.url, number: number });
                            }
                        })
                        .catch(function () {
                            // Best-effort — the call still rings and shows on the widget regardless.
                        });
                }, 700);
            }

            broadcast('connecting');

            fetch(@json(route('admin.zadarma.phone.webrtc_key')), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (! response.ok) {
                            throw new Error(data.message || 'Request failed');
                        }

                        return data;
                    });
                })
                .then(function (data) {
                    /**
                     * Dynamically created <script> elements load
                     * asynchronously by default, so loader-phone-fn.js could
                     * start executing before loader-phone-lib.js finishes
                     * (it defines zdrmWebrtcPhoneInterface, which fn needs).
                     * Chaining onload keeps them strictly sequential.
                     */
                    const libScript = document.createElement('script');
                    libScript.src = 'https://my.zadarma.com/webphoneWebRTCWidget/v8/js/loader-phone-lib.js?v=23';
                    libScript.async = false;

                    libScript.onload = function () {
                        const fnScript = document.createElement('script');
                        fnScript.src = 'https://my.zadarma.com/webphoneWebRTCWidget/v8/js/loader-phone-fn.js?v=23';
                        fnScript.async = false;

                        fnScript.onload = function () {
                            /**
                             * loader-phone-lib.js itself doesn't define
                             * zdrmWebrtcPhoneInterface — it just injects
                             * *another* batch of scripts (widget.min.js,
                             * jssip.min.js, etc.) which do, without waiting
                             * for them either. So even after both of our
                             * script tags finish loading, that class may not
                             * exist yet. Poll briefly instead of assuming.
                             */
                            const startedAt = Date.now();

                            (function waitForWidget() {
                                if (typeof window.zdrmWebrtcPhoneInterface !== 'undefined') {
                                    statusEl.style.display = 'none';

                                    window.zadarmaWidgetFn(
                                        data.key,
                                        data.login,
                                        'square',
                                        @json($zadarmaWidgetLocale),
                                        true,
                                        "{top:'24px',left:'24px'}"
                                    );

                                    broadcast('ready');

                                    fitWindowToWidget();

                                    autoDialFromQueryString();

                                    startIncomingCallWatcher();

                                    return;
                                }

                                if (Date.now() - startedAt > 10000) {
                                    showMessage('Timed out waiting for the Zadarma widget to load.');
                                    broadcast('error');

                                    return;
                                }

                                setTimeout(waitForWidget, 100);
                            })();
                        };

                        fnScript.onerror = function () {
                            showMessage('Failed to load loader-phone-fn.js');
                            broadcast('error');
                        };

                        document.body.appendChild(fnScript);
                    };

                    libScript.onerror = function () {
                        showMessage('Failed to load loader-phone-lib.js');
                        broadcast('error');
                    };

                    document.body.appendChild(libScript);
                })
                .catch(function (error) {
                    showMessage(error.message);
                    broadcast('error');
                });

            window.addEventListener('beforeunload', function () {
                broadcast('closed');
            });
        })();
    </script>
    @endunless
</body>
</html>
