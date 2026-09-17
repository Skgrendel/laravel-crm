<?php

namespace Addons\WhatsApp\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * A disconnected session takes the sales line down silently: no messages
 * arrive, no leads are captured, and nothing in the CRM looks broken. This
 * is the only thing that tells anyone.
 */
class SessionDisconnected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ?string $number,
    ) {}

    public function build()
    {
        return $this->subject(trans('whatsapp::app.mail.session-disconnected.subject'))
            ->view('whatsapp::mail.session-disconnected');
    }
}
