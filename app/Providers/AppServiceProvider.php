<?php

namespace App\Providers;

use App\Models\Attempt;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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
