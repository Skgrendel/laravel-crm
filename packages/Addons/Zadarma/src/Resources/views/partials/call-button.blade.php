@if (! empty($number))
    <button
        type="button"
        class="secondary-button flex items-center gap-1.5"
        onclick="window.zadarmaOpenPhonePopup({{ json_encode($number) }})"
    >
        <span class="icon-call text-lg"></span>

        @lang('zadarma::app.phone.call-btn')
    </button>
@endif
