<?php

namespace App\Mail\Auth;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class RegistrationConfirmedMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $firstName,
        public readonly string $email,
        public readonly string $activationUrl,
        public readonly DateTimeInterface $createdAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenue sur Statsio – votre compte est prêt',
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return new Content(
            view: 'emails.auth.registration-confirmed',
            with: [
                'logoUrl' => asset('images/mail/statsio-logo.png'),
                'createdAtLabel' => Carbon::instance($this->createdAt)->translatedFormat('d F Y à H \h i'),
                'channelsUrl' => $frontendUrl.'/mes-chaines',
                'tvUrl' => $frontendUrl.'/tvstats/programme-tv',
                'helpUrl' => $frontendUrl.'/contact',
            ],
        );
    }
}
