<?php

use App\Models\Attempt;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('session.{sessionId}', function ($user, $sessionId) {
    if ($user->isAdmin()) {
        return true;
    }

    return Attempt::where('user_id', $user->id)
        ->where('quiz_session_id', (int) $sessionId)
        ->exists();
});

Broadcast::channel('admin-dashboard', fn ($user) => $user->isAdmin());
