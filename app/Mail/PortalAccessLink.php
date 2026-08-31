<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalAccessLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $url,
        public int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your OmniSignal licence portal link',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.portal-access-link',
        );
    }
}
