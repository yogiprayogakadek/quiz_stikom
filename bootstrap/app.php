<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSingleSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Aplikasi berada di belakang reverse proxy (edge TLS provider). Tanpa ini
        // Request::ip() selalu berisi IP proxy, sehingga rate limit login 5/menit
        // berlaku untuk seluruh peserta sekaligus, bukan per pengguna.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            EnsureSingleSession::class,
            EnsurePasswordChanged::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
