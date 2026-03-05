<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class CareerApplication extends Mailable
{
    use Queueable, SerializesModels;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function envelope(): Envelope
    {
        $subject = 'New Career Application';
        if (!empty($this->payload['opening_title'])) {
            $subject .= ' - ' . $this->payload['opening_title'];
        } elseif (!empty($this->payload['role'])) {
            $subject .= ' - ' . $this->payload['role'];
        }

        return new Envelope(
            subject: $subject
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.careers.application',
            with: ['payload' => $this->payload]
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if (!empty($this->payload['cv_path'])) {
            $attachments[] = Attachment::fromPath(storage_path('app/public/' . $this->payload['cv_path']));
        }

        if (!empty($this->payload['cover_letter_path'])) {
            $attachments[] = Attachment::fromPath(storage_path('app/public/' . $this->payload['cover_letter_path']));
        }

        return $attachments;
    }
}
