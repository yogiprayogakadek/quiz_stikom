<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if (blank($request->user()->email) || $request->user()->hasVerifiedEmail()) {
            return $this->destination($request);
        }

        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
        }

        return $this->destination($request)->with('sukses', 'Email berhasil diverifikasi.');
    }

    public function send(Request $request): RedirectResponse
    {
        abort_if(blank($request->user()->email), 422);

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['email' => 'Tautan verifikasi belum dapat dikirim. Coba lagi nanti.']);
        }

        return back()->with('sukses', 'Tautan verifikasi baru telah dikirim.');
    }

    private function destination(Request $request): RedirectResponse
    {
        return redirect()->route(
            $request->user()->isPlatformAdmin()
                ? 'master.organizations.index'
                : ($request->user()->isAdmin() ? 'admin.dashboard' : 'peserta.dashboard'),
        );
    }
}
