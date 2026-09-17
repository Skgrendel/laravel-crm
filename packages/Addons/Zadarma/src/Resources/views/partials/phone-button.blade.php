<div
    id="zadarma-phone-button"
    title="@lang('zadarma::app.phone.button-title')"
    style="
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 10002;
        width: 52px;
        height: 52px;
        border-radius: 9999px;
        background: var(--brand-color, #0E90D9);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 10px rgba(0, 0, 0, .25);
    "
>
    <span
        id="zadarma-phone-button-dot"
        style="
            position: absolute;
            top: 4px;
            right: 4px;
            width: 10px;
            height: 10px;
            border-radius: 9999px;
            background: #9ca3af;
            border: 2px solid #fff;
        "
    ></span>

    <span
        class="icon-call"
        style="font-size: 22px;"
    ></span>
</div>

<div
    id="zadarma-incoming-banner"
    style="
        display: none;
        position: fixed;
        right: 20px;
        bottom: 84px;
        z-index: 10002;
        width: 260px;
        padding: 12px;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(0, 0, 0, .2);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Inter, Arial, sans-serif;
        font-size: 13px;
        color: #111827;
    "
>
    <div style="font-weight: 600; margin-bottom: 4px;">@lang('zadarma::app.phone.incoming')</div>
    <div id="zadarma-incoming-banner-number" style="color: #6b7280; margin-bottom: 8px;"></div>
    <a
        id="zadarma-incoming-banner-link"
        href="#"
        style="display: inline-block; padding: 6px 10px; border-radius: 6px; background: var(--brand-color, #0E90D9); color: #fff; text-decoration: none; font-size: 12px;"
    >@lang('zadarma::app.phone.view-lead-btn')</a>
</div>

<script>
    /**
     * Global (not scoped to this partial) so the Lead view's "Llamar"
     * button — injected separately — can reuse the same open-or-focus
     * logic instead of duplicating it.
     */
    window.zadarmaOpenPhonePopup = function (dialNumber) {
        const url = @json(route('admin.zadarma.phone.show'));

        let popup = window.open('', 'zadarma_softphone');

        if (popup && popup.zdrmWebPhone && typeof popup.zdrmWebPhone.call === 'function') {
            // Already open and registered — dial directly, no navigation.
            if (dialNumber) {
                popup.zdrmWebPhone.call(dialNumber);
            }

            popup.focus();

            return;
        }

        popup.location.href = dialNumber ? (url + '?dial=' + encodeURIComponent(dialNumber)) : url;
        popup.focus();
    };

    (function () {
        const button = document.getElementById('zadarma-phone-button');
        const dot = document.getElementById('zadarma-phone-button-dot');
        const banner = document.getElementById('zadarma-incoming-banner');
        const bannerNumber = document.getElementById('zadarma-incoming-banner-number');
        const bannerLink = document.getElementById('zadarma-incoming-banner-link');

        const statusColors = {
            connecting: '#f59e0b',
            ready: '#22c55e',
            incoming: '#ef4444',
            error: '#ef4444',
            closed: '#9ca3af',
        };

        let bannerTimeout;

        button.addEventListener('click', function () {
            window.zadarmaOpenPhonePopup();
        });

        if ('BroadcastChannel' in window) {
            const channel = new BroadcastChannel('zadarma-softphone');

            channel.onmessage = function (event) {
                const data = event.data || {};

                if (data.status) {
                    dot.style.background = statusColors[data.status] || statusColors.closed;
                }

                if (data.status === 'incoming-lead-match') {
                    bannerNumber.textContent = data.number;
                    bannerLink.href = data.url;
                    banner.style.display = 'block';

                    clearTimeout(bannerTimeout);
                    bannerTimeout = setTimeout(function () {
                        banner.style.display = 'none';
                    }, 20000);
                }
            };
        }
    })();
</script>
