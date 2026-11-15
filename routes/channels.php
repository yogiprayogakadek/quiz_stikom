<?php

use App\Models\Attempt;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('session.{sessionId}', function ($user, $sessionId) {
    if ($user->isAdmin()) {
        return true;
    }

    return Attempt::where('user_id', $user->id)
        ->where('quiz_session_id', (int) $sessionId)
        ->exists();
});

Broadcast::channel('admin-dashboard.{organizationId}', function ($user, int $organizationId) {
    return $user->isAdmin()
        && app(OrganizationContext::class)->id() === $organizationId
        && $user->organizations()
            ->whereKey($organizationId)
            ->wherePivot('status', 'active')
            ->exists();
});
