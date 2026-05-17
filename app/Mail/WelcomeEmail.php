<?php

namespace App\Mail;

use App\Models\Discount;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
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
            subject: '🎉 Welcome to EatWella, ' . $this->user->name . '!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
