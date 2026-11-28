<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Undangan Workspace</title>
</head>
<body style="margin:0;padding:24px;background-color:#f8fafc;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;color:#0f172a;line-height:1.6;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background-color:#ffffff;border:1px solid #cbd5e1;border-radius:12px;padding:28px;">
        <tr>
            <td>
                <h1 style="margin:0 0 12px 0;font-size:20px;color:#0f172a;">Undangan {{ $roleLabel }} Workspace</h1>
                <p style="margin:0 0 16px 0;color:#334155;">
                    Halo <strong>{{ $recipient->nama }}</strong>, akun Anda telah didaftarkan sebagai <strong>{{ $roleLabel }}</strong> pada workspace <strong>{{ $organization->name }}</strong>.
                </p>
                <div style="margin:0 0 18px 0;padding:14px 16px;background-color:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;color:#0f172a;">
                    <div><strong>Username:</strong> {{ $recipient->username }}</div>
                    @if ($temporaryPassword !== null)
                        <div style="margin-top:6px;"><strong>Password Sementara:</strong> <code style="font-family:monospace;font-size:14px;">{{ $temporaryPassword }}</code></div>
                        <div style="margin-top:6px;font-size:12px;color:#475569;">Anda wajib mengganti password sementara ini saat login pertama.</div>
                    @endif
                </div>
                <p style="margin:0 0 16px 0;">
                    <a href="{{ $loginUrl }}" style="display:inline-block;padding:12px 20px;background-color:#1d4ed8;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">
                        Masuk ke Workspace
                    </a>
                </p>
                <p style="margin:0;font-size:13px;color:#475569;">
                    Tautan login: <a href="{{ $loginUrl }}" style="color:#1d4ed8;">{{ $loginUrl }}</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
