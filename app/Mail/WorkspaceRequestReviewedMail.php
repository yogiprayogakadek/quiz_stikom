<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\WorkspaceRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WorkspaceRequestReviewedMail extends Mailable
{
    public function __construct(
        public WorkspaceRequest $workspaceRequest,
        public ?Organization $organization = null,
    ) {}

    public function envelope(): Envelope
    {
        $disetujui = $this->workspaceRequest->status === WorkspaceRequest::STATUS_APPROVED;
        $status = $disetujui ? 'Disetujui' : 'Ditolak';

        return new Envelope(
            subject: "Pengajuan Workspace {$this->workspaceRequest->name} {$status}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.workspace-request-reviewed',
            with: [
                'workspaceRequest' => $this->workspaceRequest,
                'organization' => $this->organization,
                'loginUrl' => $this->organization
                    ? route('organizations.login', $this->organization)
                    : route('home'),
            ],
        );
    }
}
