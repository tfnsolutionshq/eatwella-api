<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtp extends Mailable
{
    use SerializesModels;

    public function __construct(public User $user, public string $otp) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your EatWella Password Reset OTP');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset-otp');
    }

    public function attachments(): array
    {
        return [];
    }
}
