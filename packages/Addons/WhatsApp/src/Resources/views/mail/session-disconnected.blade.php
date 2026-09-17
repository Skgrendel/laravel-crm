<p>@lang('whatsapp::app.mail.session-disconnected.greeting')</p>

<p>
    @if ($number)
        @lang('whatsapp::app.mail.session-disconnected.body-with-number', ['number' => $number])
    @else
        @lang('whatsapp::app.mail.session-disconnected.body')
    @endif
</p>

<p>
    <a href="{{ route('admin.settings.whatsapp.index') }}">
        @lang('whatsapp::app.mail.session-disconnected.action')
    </a>
</p>
