<?php

namespace App\Http\Middleware;

use App\Models\OrganizationMembership;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackParticipantPresence
{
    public const INTERVAL_SECONDS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organizationId = app(OrganizationContext::class)->id();

        if ($user?->isPeserta() && $organizationId !== null) {
            $sessionKey = self::sessionKey($organizationId);
            $lastRecordedAt = (int) $request->session()->get($sessionKey, 0);

            if ($lastRecordedAt <= now()->timestamp - self::INTERVAL_SECONDS) {
                OrganizationMembership::query()
                    ->where('organization_id', $organizationId)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->update(['last_seen_at' => now()]);

                $request->session()->put($sessionKey, now()->timestamp);
            }
        }

        return $next($request);
    }

    public static function sessionKey(int $organizationId): string
    {
        return 'participant_presence_recorded_at_'.$organizationId;
    }
}
