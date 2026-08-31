<?php

namespace App\Mail\Channel;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChannelInvitationMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $channelName,
        public readonly string $roleLabel,
        public readonly string $inviterName,
        public readonly string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->inviterName} vous invite à rejoindre {$this->channelName} sur Statsio",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.channel.invitation',
            with: [
                'logoUrl' => asset('images/mail/statsio-logo.png'),
            ],
        );
    }
}
