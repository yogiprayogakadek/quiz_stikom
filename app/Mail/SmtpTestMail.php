<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;

class SmtpTestMail extends Mailable
{
    public function __construct(public string $siteName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Uji SMTP '.$this->siteName);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.smtp-test', with: ['siteName' => $this->siteName]);
    }
}
