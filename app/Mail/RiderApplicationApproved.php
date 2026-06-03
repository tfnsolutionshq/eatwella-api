<?php

namespace App\Mail;

use App\Models\RiderApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RiderApplicationApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RiderApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Application Approved – Activate Your Account');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.rider-application-approved');
    }
}
