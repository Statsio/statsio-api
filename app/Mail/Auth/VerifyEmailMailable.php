<?php

namespace App\Mail\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $firstName,
        public readonly string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmez votre adresse e-mail – Statsio',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.verify-email',
        );
    }
}
