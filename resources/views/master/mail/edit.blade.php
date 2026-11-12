<x-layouts.admin judul="Email platform" keterangan="Satu konfigurasi SMTP untuk verifikasi akun, reset password, dan email uji.">
    <div class="max-w-4xl space-y-6">
        <section @class([
            'rounded-2xl border p-5 sm:p-6',
            'border-leaf/30 bg-leaf-soft' => filled($settings->mail_host),
            'border-outline bg-page' => ! filled($settings->mail_host),
        ]) aria-labelledby="status-email">
            <div class="flex items-start gap-3">
                <span @class([
                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                    'bg-leaf/15 text-leaf-deep' => filled($settings->mail_host),
                    'bg-surface-sunken text-ink-muted' => ! filled($settings->mail_host),
                ])>
                    <x-icon name="mail" :size="20" />
                </span>
                <div>
                    <h2 id="status-email" class="text-xl">
                        {{ filled($settings->mail_host) ? 'SMTP database aktif' : 'Memakai konfigurasi env' }}
                    </h2>
                    <p class="mt-1 text-sm leading-relaxed text-ink-muted">
                        @if (filled($settings->mail_host))
                            Email keluar memakai {{ $settings->mail_host }}:{{ $settings->mail_port }} dengan enkripsi {{ strtoupper($settings->mail_encryption ?: 'TLS') }}.
                        @else
                            Isi host SMTP untuk mengganti konfigurasi <code>MAIL_*</code> tanpa deploy. Selama host kosong, aplikasi tetap memakai env.
                        @endif
                    </p>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('master.mail.update') }}" class="card space-y-5" data-progress-message="Menyimpan pengaturan email...">
            @csrf
            @method('PUT')

            <div>
                <h2 class="text-xl">Server SMTP</h2>
                <p class="mt-1 text-sm text-ink-muted">Gunakan kredensial dari penyedia email atau server mail organisasi.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="mail_host" class="label">Host SMTP</label>
                    <input id="mail_host" name="mail_host" class="input" maxlength="255" value="{{ old('mail_host', $settings->mail_host) }}" placeholder="smtp.contoh.com" autocomplete="off" @error('mail_host') aria-invalid="true" aria-describedby="mail_host_error" @enderror>
                    <p class="help mt-1">Kosongkan untuk kembali memakai konfigurasi env.</p>
                    @error('mail_host') <p id="mail_host_error" class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mail_port" class="label">Port</label>
                    <input id="mail_port" name="mail_port" type="number" min="1" max="65535" class="input" value="{{ old('mail_port', $settings->mail_port ?: 587) }}" placeholder="587" @error('mail_port') aria-invalid="true" aria-describedby="mail_port_error" @enderror>
                    @error('mail_port') <p id="mail_port_error" class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mail_encryption" class="label">Enkripsi</label>
                    <select id="mail_encryption" name="mail_encryption" class="input" @error('mail_encryption') aria-invalid="true" aria-describedby="mail_encryption_error" @enderror>
                        <option value="tls" @selected(old('mail_encryption', $settings->mail_encryption ?: 'tls') === 'tls')>TLS (biasanya 587)</option>
                        <option value="ssl" @selected(old('mail_encryption', $settings->mail_encryption ?: 'tls') === 'ssl')>SSL (biasanya 465)</option>
                    </select>
                    @error('mail_encryption') <p id="mail_encryption_error" class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mail_username" class="label">Username SMTP</label>
                    <input id="mail_username" name="mail_username" class="input" maxlength="255" value="{{ old('mail_username', $settings->mail_username) }}" autocomplete="off" @error('mail_username') aria-invalid="true" aria-describedby="mail_username_error" @enderror>
                    @error('mail_username') <p id="mail_username_error" class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mail_password" class="label">Password SMTP</label>
                    <input id="mail_password" name="mail_password" type="password" class="input" maxlength="500" autocomplete="new-password" placeholder="{{ filled($settings->mail_password) ? 'Password tersimpan, isi untuk mengganti' : 'Masukkan password SMTP' }}" @error('mail_password') aria-invalid="true" aria-describedby="mail_password_error" @enderror>
                    <p class="help mt-1">Kosongkan untuk mempertahankan password lama. Password tidak pernah ditampilkan kembali.</p>
                    @error('mail_password') <p id="mail_password_error" class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="border-t border-outline pt-5">
                <h3 class="text-lg">Pengirim</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="mail_from_address" class="label">Alamat pengirim</label>
                        <input id="mail_from_address" name="mail_from_address" type="email" class="input" maxlength="255" value="{{ old('mail_from_address', $settings->mail_from_address ?: config('mail.from.address')) }}" placeholder="no-reply@contoh.com" autocomplete="email" @error('mail_from_address') aria-invalid="true" aria-describedby="mail_from_address_error" @enderror>
                        @error('mail_from_address') <p id="mail_from_address_error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="mail_from_name" class="label">Nama pengirim</label>
                        <input id="mail_from_name" name="mail_from_name" class="input" maxlength="120" value="{{ old('mail_from_name', $settings->mail_from_name ?: config('mail.from.name')) }}" placeholder="STIKOM Quiz" @error('mail_from_name') aria-invalid="true" aria-describedby="mail_from_name_error" @enderror>
                        @error('mail_from_name') <p id="mail_from_name_error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end border-t border-outline pt-5">
                <button type="submit" class="btn btn-primary">
                    <x-icon name="check" :size="18" />
                    Simpan pengaturan
                </button>
            </div>
        </form>

        <section class="card" aria-labelledby="test-email-heading">
            <h2 id="test-email-heading" class="text-xl">Kirim email uji</h2>
            <p class="mt-1 text-sm text-ink-muted">Simpan pengaturan dulu, lalu kirim email ke alamat yang bisa kamu periksa.</p>

            <form method="POST" action="{{ route('master.mail.test') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end" data-progress-message="Mengirim email uji...">
                @csrf
                <div class="flex-1">
                    <label for="test_email" class="label">Alamat tujuan</label>
                    <input id="test_email" name="test_email" type="email" class="input" maxlength="255" value="{{ old('test_email', auth()->user()->email) }}" required autocomplete="email" @error('test_email') aria-invalid="true" aria-describedby="test_email_error" @enderror>
                    @error('test_email') <p id="test_email_error" class="field-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn-secondary shrink-0">
                    <x-icon name="mail" :size="18" />
                    Kirim email uji
                </button>
            </form>
        </section>

        <section class="card" aria-labelledby="email-log-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="email-log-heading" class="text-xl">Riwayat email terkirim</h2>
                    <p class="mt-1 text-sm text-ink-muted">Catatan email yang berhasil dikirim. Isi pesan, token, dan password tidak disimpan.</p>
                </div>
                <span class="pill">{{ $emailLogs->total() }} email</span>
            </div>

            @if ($emailLogs->isEmpty())
                <p class="mt-5 rounded-xl border border-dashed border-outline p-5 text-sm text-ink-muted">Belum ada email yang tercatat.</p>
            @else
                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-outline text-xs font-bold uppercase tracking-wide text-ink-subtle">
                                <th class="px-3 py-3">Waktu</th>
                                <th class="px-3 py-3">Penerima</th>
                                <th class="px-3 py-3">Subjek</th>
                                <th class="px-3 py-3">Workspace</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline">
                            @foreach ($emailLogs as $log)
                                <tr>
                                    <td class="whitespace-nowrap px-3 py-3 text-ink-muted">{{ $log->sent_at->format('d M Y H:i') }}</td>
                                    <td class="max-w-[220px] px-3 py-3"><span class="break-all">{{ $log->recipients }}</span></td>
                                    <td class="max-w-[280px] px-3 py-3">
                                        <span class="block break-words">{{ $log->subject ?: '(tanpa subjek)' }}</span>
                                        @if (filled($log->type))
                                            <span class="mt-0.5 block text-xs text-ink-subtle">{{ class_basename($log->type) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-ink-muted">{{ $log->organization?->name ?? 'Platform' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($emailLogs->hasPages())
                    <div class="mt-6">{{ $emailLogs->links() }}</div>
                @endif
            @endif
        </section>
    </div>
</x-layouts.admin>
