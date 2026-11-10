<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Mail\SmtpTestMail;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Services\PlatformMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class MailSettingController extends Controller
{
    public function edit(): View
    {
        return view('master.mail.edit', ['settings' => AppSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'required_with:mail_host', 'integer', 'between:1,65535'],
            'mail_encryption' => ['nullable', 'required_with:mail_host', 'in:tls,ssl'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:500'],
            'mail_from_address' => ['nullable', 'required_with:mail_host', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
        ]);

        $data['mail_host'] = filled($data['mail_host'] ?? null) ? trim((string) $data['mail_host']) : null;

        if (blank($data['mail_password'] ?? null)) {
            unset($data['mail_password']);
        }

        $settings = AppSetting::query()->firstOrNew(['id' => 1]);
        $settings->fill($data)->save();

        AuditLog::record('platform_mail_updated', 'platform', 1, [
            'host' => $settings->mail_host,
            'port' => $settings->mail_port,
            'from' => $settings->mail_from_address,
        ], null);

        return back()->with('sukses', 'Pengaturan email platform disimpan.');
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        PlatformMail::apply();

        try {
            Mail::to($data['test_email'])->send(new SmtpTestMail(app('app.settings')->site_name));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'test_email' => 'Email uji gagal dikirim. Periksa host, port, enkripsi, dan kredensial SMTP.',
            ]);
        }

        return back()->with('sukses', 'Email uji dikirim ke '.$data['test_email'].'.');
    }
}
