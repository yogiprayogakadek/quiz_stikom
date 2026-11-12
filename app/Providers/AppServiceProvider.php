<?php

namespace App\Providers;

use App\Listeners\LogSentEmail;
use App\Models\AppSetting;
use App\Models\OrganizationSetting;
use App\Models\WorkspaceRequest;
use App\Services\PlatformMail;
use App\Support\OrganizationContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(OrganizationContext::class);
        $this->app->scoped('app.settings', function () {
            $organizationId = app(OrganizationContext::class)->id();

            return $organizationId === null
                ? AppSetting::current()
                : OrganizationSetting::effective($organizationId);
        });
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            PlatformMail::apply();
        }

        View::composer('*', fn ($view) => $view->with([
            'appSettings' => app('app.settings'),
            'orgTimezone' => app(OrganizationContext::class)->timezone(),
        ]));

        View::composer('components.layouts.admin', function ($view) {
            $user = auth()->user();

            $view->with([
                'hasOwnedWorkspace' => $user?->ownedOrganizations()->exists() ?? false,
                'pendingWorkspaceRequestCount' => $user && $user->isPlatformAdmin()
                    ? WorkspaceRequest::query()->where('status', WorkspaceRequest::STATUS_PENDING)->count()
                    : 0,
            ]);
        });

        Event::listen(MessageSent::class, LogSentEmail::class);

        // TLS diterminasi di edge, jadi request ke PHP selalu http. Paksa https
        // hanya kalau APP_URL memang https, supaya redirect dan asset tidak
        // diturunkan ke http.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('login', function (Request $request) {
            $username = mb_strtolower((string) $request->input('username'));

            return Limit::perMinute(5)->by($username.'|'.$request->ip());
        });

        RateLimiter::for('attempt-events', function (Request $request) {
            return Limit::perMinute(30)->by('user:'.$request->user()?->id);
        });

        RateLimiter::for('answers', function (Request $request) {
            return Limit::perMinute(90)->by('user:'.$request->user()?->id);
        });
    }
}
