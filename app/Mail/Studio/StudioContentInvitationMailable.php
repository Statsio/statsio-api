<?php

namespace App\Mail\Studio;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudioContentInvitationMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $contentTitle,
        public readonly string $inviterName,
        public readonly string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->inviterName} vous invite à collaborer sur « {$this->contentTitle} »",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.studio.content-invitation',
            with: [
                'logoUrl' => asset('images/mail/statsio-logo.png'),
            ],
        );
    }
}
