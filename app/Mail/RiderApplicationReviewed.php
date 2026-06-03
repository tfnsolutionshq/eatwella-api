<?php

namespace App\Mail;

use App\Models\RiderApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RiderApplicationReviewed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RiderApplication $application) {}

    public function envelope(): Envelope
    {
        $subject = $this->application->status === 'approved'
            ? "You're approved! Activate your EatWella rider account"
            : 'Update on your EatWella rider application';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.rider-application-approved');
    }
}
