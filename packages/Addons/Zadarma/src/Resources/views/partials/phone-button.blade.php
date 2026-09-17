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

<script>
    (function () {
        const button = document.getElementById('zadarma-phone-button');
        const dot = document.getElementById('zadarma-phone-button-dot');

        const statusColors = {
            connecting: '#f59e0b',
            ready: '#22c55e',
            error: '#ef4444',
            closed: '#9ca3af',
        };

        let popup = null;

        button.addEventListener('click', function () {
            if (popup && ! popup.closed) {
                popup.focus();

                return;
            }

            popup = window.open(
                @json(route('admin.zadarma.phone.show')),
                'zadarma_softphone',
                'width=400,height=600,resizable=yes,scrollbars=yes'
            );
        });

        if ('BroadcastChannel' in window) {
            const channel = new BroadcastChannel('zadarma-softphone');

            channel.onmessage = function (event) {
                const status = event.data && event.data.status;

                dot.style.background = statusColors[status] || statusColors.closed;
            };
        }
    })();
</script>
