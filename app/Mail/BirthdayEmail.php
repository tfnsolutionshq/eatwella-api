<?php

namespace App\Mail;

use App\Models\Discount;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BirthdayEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?Discount $menuDiscount,
        public ?Discount $freeDeliveryDiscount
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎂 Happy Birthday ' . $this->user->name . '! A gift from EatWella',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.birthday',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
