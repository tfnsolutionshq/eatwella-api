<?php

namespace App\Mail;

use App\Models\RiderApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RiderApplicationReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RiderApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Application Received – Pending Approval');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.rider-application-received');
    }
}
