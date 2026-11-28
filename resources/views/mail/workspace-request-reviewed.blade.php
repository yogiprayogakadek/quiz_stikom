<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Status Pengajuan Workspace</title>
</head>
<body style="margin:0;padding:24px;background-color:#f8fafc;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;color:#0f172a;line-height:1.6;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background-color:#ffffff;border:1px solid #cbd5e1;border-radius:12px;padding:28px;">
        <tr>
            <td>
                @if ($workspaceRequest->status === \App\Models\WorkspaceRequest::STATUS_APPROVED)
                    <h1 style="margin:0 0 12px 0;font-size:20px;color:#0f172a;">Workspace Anda Disetujui</h1>
                    <p style="margin:0 0 16px 0;color:#334155;">
                        Pengajuan workspace <strong>{{ $workspaceRequest->name }}</strong> telah disetujui oleh pengelola platform dan siap digunakan.
                    </p>
                    @if ($organization)
                        <p style="margin:0 0 20px 0;">
                            <a href="{{ $loginUrl }}" style="display:inline-block;padding:12px 20px;background-color:#1d4ed8;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">
                                Buka Halaman Login Workspace
                            </a>
                        </p>
                        <p style="margin:0;font-size:13px;color:#475569;">
                            Tautan langsung: <a href="{{ $loginUrl }}" style="color:#1d4ed8;">{{ $loginUrl }}</a>
                        </p>
                    @endif
                @else
                    <h1 style="margin:0 0 12px 0;font-size:20px;color:#0f172a;">Pengajuan Workspace Belum Dapat Disetujui</h1>
                    <p style="margin:0 0 12px 0;color:#334155;">
                        Pengajuan workspace <strong>{{ $workspaceRequest->name }}</strong> telah ditinjau oleh pengelola platform dengan catatan berikut:
                    </p>
                    <div style="margin:0 0 16px 0;padding:12px 16px;background-color:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;color:#0f172a;">
                        {{ $workspaceRequest->rejection_reason }}
                    </div>
                    <p style="margin:0;font-size:13px;color:#475569;">
                        Anda dapat memperbaiki detail pengajuan melalui menu Workspace pada panel admin.
                    </p>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
