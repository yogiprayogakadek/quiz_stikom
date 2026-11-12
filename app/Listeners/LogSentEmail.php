<?php

namespace App\Listeners;

use App\Models\EmailLog;
use App\Support\OrganizationContext;
use Illuminate\Mail\Events\MessageSent;
use Throwable;

class LogSentEmail
{
    public function handle(MessageSent $event): void
    {
        try {
            $recipients = collect($event->message->getTo())
                ->map(fn ($address) => $address->getAddress())
                ->filter()
                ->implode(', ');

            if ($recipients === '') {
                return;
            }

            EmailLog::create([
                'organization_id' => app(OrganizationContext::class)->id(),
                'recipients' => $recipients,
                'subject' => $event->message->getSubject(),
                'type' => $event->data['__laravel_mailable'] ?? null,
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
