<?php

namespace App\Mail\Support;

use App\Models\Support\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ContactConfirmationMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ContactMessage $contactMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre demande a bien été reçue – Statsio',
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return new Content(
            view: 'emails.support.contact-confirmation',
            with: [
                'firstName' => Str::of($this->contactMessage->name)->trim()->before(' ')->toString(),
                'reference' => 'SIO-'.str_pad((string) $this->contactMessage->id, 5, '0', STR_PAD_LEFT),
                'reasonLabel' => $this->contactMessage->reason->label(),
                'sentAt' => $this->contactMessage->created_at->translatedFormat('d F Y à H \h i'),
                'messageExcerpt' => Str::limit($this->contactMessage->message, 220),
                'logoUrl' => asset('images/mail/statsio-logo.png'),
                'trackUrl' => $frontendUrl.'/contact',
                'helpUrl' => $frontendUrl.'/contact',
                'channelsUrl' => $frontendUrl.'/chaines',
                'tvUrl' => $frontendUrl.'/tvstats/programme-tv',
            ],
        );
    }
}
