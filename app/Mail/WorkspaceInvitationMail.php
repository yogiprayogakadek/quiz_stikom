<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WorkspaceInvitationMail extends Mailable
{
    public function __construct(
        public Organization $organization,
        public User $recipient,
        public string $roleLabel,
        public ?string $temporaryPassword = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Akses {$this->roleLabel} untuk Workspace {$this->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.workspace-invitation',
            with: [
                'organization' => $this->organization,
                'recipient' => $this->recipient,
                'roleLabel' => $this->roleLabel,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => route('organizations.login', $this->organization),
            ],
        );
    }
}
