@if (! empty($number))
    {{--
        Pushed to the layout's styles stack, not inlined here: this partial
        renders inside `#app`, and Vue takes that DOM as its template on
        mount, dropping any style tag it finds there.

        Colors live in CSS rather than Tailwind utilities because the Admin
        package's Tailwind build only scans its own `src/Resources/**` — any
        class an addon uses that core doesn't already use is purged out of
        the compiled CSS. The layout utilities below survive because core's
        own action tiles use them.
    --}}
    @pushOnce('styles')
        <style>
            .zadarma-call-tile {
                background-color: #e9d5ff;
                color: #581c87;
            }

            .zadarma-call-tile:hover {
                border-color: #c084fc;
            }
        </style>
    @endPushOnce

    <div>
        <button
            type="button"
            class="zadarma-call-tile flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent font-medium transition-all"
            onclick="window.zadarmaOpenPhonePopup({{ json_encode($number) }})"
        >
            <span class="icon-call text-2xl"></span>

            @lang('zadarma::app.phone.call-btn')
        </button>
    </div>
@endif
