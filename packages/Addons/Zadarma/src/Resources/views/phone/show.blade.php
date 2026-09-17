<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@lang('zadarma::app.phone.page-title')</title>

    <style>
        html, body {
            height: 100%;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, Arial, sans-serif;
            background: #111827;
            color: #e5e7eb;
        }

        #zadarma-phone-status {
            display: flex;
            height: 100%;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
            font-size: 14px;
            line-height: 1.5;
        }
    </style>
</head>
@php
    $zadarmaWidgetLocales = ['ru', 'en', 'es', 'fr', 'de', 'pl', 'ua'];
    $zadarmaWidgetLocale = in_array(app()->getLocale(), $zadarmaWidgetLocales) ? app()->getLocale() : 'en';
@endphp
<body>
    @if (empty($extension))
        <div id="zadarma-phone-status">{{ trans('zadarma::app.phone.no-extension') }}</div>
    @else
        <div id="zadarma-phone-status">@lang('zadarma::app.phone.loading')</div>
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
                    const libScript = document.createElement('script');
                    libScript.src = 'https://my.zadarma.com/webphoneWebRTCWidget/v8/js/loader-phone-lib.js?v=23';

                    const fnScript = document.createElement('script');
                    fnScript.src = 'https://my.zadarma.com/webphoneWebRTCWidget/v8/js/loader-phone-fn.js?v=23';

                    fnScript.onload = function () {
                        statusEl.style.display = 'none';

                        window.zadarmaWidgetFn(
                            data.key,
                            data.login,
                            'square',
                            @json($zadarmaWidgetLocale),
                            true,
                            "{top:'0px',left:'0px'}"
                        );

                        broadcast('ready');
                    };

                    document.body.appendChild(libScript);
                    document.body.appendChild(fnScript);
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
