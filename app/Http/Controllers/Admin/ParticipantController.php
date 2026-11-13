<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreParticipantRequest;
use App\Http\Requests\UpdateParticipantRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OrganizationUsage;
use App\Services\ParticipantLinking;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParticipantController extends Controller
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function index(Request $request): View
    {
        $request->session()->forget('participant_link_candidate');
        $cari = trim((string) $request->query('q'));

        // shortcut: client-side pagination up to 2000 rows for real-time responsiveness, upgrade to AJAX datatable if huge
        $participants = User::where('role', 'peserta')
            ->inCurrentOrganization()
            ->when($cari !== '', function ($query) use ($cari) {
                $query->where(fn ($q) => $q
                    ->where('nama', 'like', "%{$cari}%")
                    ->orWhere('username', 'like', "%{$cari}%"));
            })
            ->withCount([
                'attempts',
                'organizations as active_workspace_count' => fn ($query) => $query->where('organization_memberships.status', 'active'),
            ])
            ->orderBy('nama')
            ->get();

        return view('admin.participants.index', compact('participants', 'cari'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->boolean('batal')) {
            $request->session()->forget('participant_link_candidate');

            return redirect()->route('admin.participants.create');
        }

        $linkCandidate = $request->session()->get('participant_link_candidate');

        return view('admin.participants.create', compact('linkCandidate'));
    }

    public function store(
        StoreParticipantRequest $request,
        OrganizationUsage $usage,
        ParticipantLinking $linking,
    ): RedirectResponse {
        $data = $request->validated();
        $organizationId = app(OrganizationContext::class)->id();
        $request->session()->forget('participant_link_candidate');

        $existing = $linking->findExisting($data['username']);

        if ($existing !== null) {
            if ($existing->trashed() || ! $existing->isPeserta()) {
                throw ValidationException::withMessages([
                    'username' => 'Username sudah dipakai akun lain.',
                ]);
            }

            if (! $linking->namesMatch($existing, $data['nama'])) {
                throw ValidationException::withMessages([
                    'username' => 'NIM/username ini sudah terdaftar dengan nama berbeda. Periksa ejaan nama, gunakan nama sesuai akun, atau minta master platform menautkan dengan konfirmasi nama berbeda.',
                ]);
            }

            if ($linking->isActiveMember($existing, $organizationId)) {
                return redirect()
                    ->route('admin.participants.index')
                    ->with('sukses', 'Peserta '.$existing->nama.' sudah ada di workspace ini.');
            }

            if (! $usage->canAddParticipant($organizationId)) {
                throw ValidationException::withMessages([
                    'nama' => 'Kuota peserta workspace penuh ('.$usage->participantLimit($organizationId).'). Hubungi master platform untuk menaikkan batas.',
                ]);
            }

            $request->session()->put('participant_link_candidate', [
                'id' => $existing->id,
                'nama' => $existing->nama,
                'username' => $existing->username,
                'email_masked' => $linking->maskedEmail($existing->email),
                'email_verified' => $existing->hasVerifiedEmail(),
            ]);

            return redirect()->route('admin.participants.create')->withInput();
        }

        if (blank($data['email'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => 'Email wajib diisi untuk akun baru.',
            ]);
        }

        if (User::withTrashed()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Email sudah dipakai akun lain.',
            ]);
        }

        if (! $usage->canAddParticipant($organizationId)) {
            throw ValidationException::withMessages([
                'nama' => 'Kuota peserta workspace penuh ('.$usage->participantLimit($organizationId).'). Hubungi master platform untuk menaikkan batas.',
            ]);
        }

        $password = $data['password'] ?? null;
        $dibuat = null;

        if (blank($password)) {
            $password = $this->buatPassword();
            $dibuat = $password;
        }

        $participant = User::create([
            'nama' => $data['nama'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $password,
            'role' => 'peserta',
            'must_change_password' => true,
        ]);
        $participant->forceFill(['email_verified_at' => now()])->save();
        $participant->organizations()->syncWithoutDetaching([
            $organizationId => [
                'role' => 'participant',
                'status' => 'active',
            ],
        ]);
        AuditLog::record('participant_added', 'user', $participant->id, [
            'name' => $participant->nama,
            'username' => $participant->username,
        ]);

        return redirect()
            ->route('admin.participants.index')
            ->with('sukses', 'Peserta '.$data['nama'].' ditambahkan.')
            ->with('password_baru', $dibuat !== null ? ['username' => $data['username'], 'password' => $dibuat] : null);
    }

    public function link(Request $request, OrganizationUsage $usage, ParticipantLinking $linking): RedirectResponse
    {
        $candidate = $request->session()->pull('participant_link_candidate');

        if (! is_array($candidate) || ! isset($candidate['id'], $candidate['username'], $candidate['nama'])) {
            return redirect()
                ->route('admin.participants.create')
                ->withErrors(['username' => 'Sesi tautan sudah berakhir. Masukkan NIM lagi.']);
        }

        $participant = User::query()->find($candidate['id']);
        $organization = app(OrganizationContext::class)->current();
        $organizationId = $organization?->id;

        if (! $participant
            || ! $participant->isPeserta()
            || $organizationId === null
            || $participant->username !== $candidate['username']
            || ! $linking->namesMatch($participant, $candidate['nama'])) {
            return redirect()
                ->route('admin.participants.create')
                ->withErrors(['username' => 'Akun tidak lagi tersedia. Masukkan NIM lagi.']);
        }

        if ($linking->isActiveMember($participant, $organizationId)) {
            return redirect()
                ->route('admin.participants.index')
                ->with('sukses', 'Peserta '.$participant->nama.' sudah ada di workspace ini.');
        }

        if (! $usage->canAddParticipant($organizationId)) {
            throw ValidationException::withMessages([
                'nama' => 'Kuota peserta workspace penuh ('.$usage->participantLimit($organizationId).'). Hubungi master platform untuk menaikkan batas.',
            ]);
        }

        $linking->link($participant, $organization, 'admin_link');

        $pesan = 'Peserta '.$participant->nama.' ditambahkan ke workspace ini menggunakan akun yang sama.';
        if (blank($participant->email)) {
            $pesan .= ' Akun ini belum punya email, jadi belum bisa memakai reset password mandiri.';
        } elseif (! $participant->hasVerifiedEmail()) {
            $pesan .= $participant->isSharedParticipant()
                ? ' Email akun ini belum terverifikasi; minta master platform memverifikasi.'
                : ' Email akun ini belum terverifikasi. Anda dapat memverifikasinya dari daftar peserta.';
        }

        return redirect()->route('admin.participants.index')->with('sukses', $pesan);
    }

    public function edit(User $participant): View
    {
        $this->authorizeParticipant($participant);
        $shared = $participant->isSharedParticipant();

        return view('admin.participants.edit', compact('participant', 'shared'));
    }

    public function update(UpdateParticipantRequest $request, User $participant): RedirectResponse
    {
        $this->authorizeParticipant($participant);
        $this->guardSharedParticipant($participant);

        $data = $request->validated();

        $participant->nama = $data['nama'];
        $participant->username = $data['username'];

        if (array_key_exists('email', $data)) {
            $participant->email = $data['email'];
        }

        if (filled($data['password'] ?? null)) {
            $participant->password = $data['password'];
        }

        $participant->save();

        return redirect()
            ->route('admin.participants.index')
            ->with('sukses', 'Data peserta diperbarui.');
    }

    public function resetPassword(User $participant): RedirectResponse
    {
        $this->authorizeParticipant($participant);
        $this->guardSharedParticipant($participant);

        $password = $this->buatPassword();
        $participant->password = $password;
        $participant->must_change_password = true;
        $participant->current_session_token = null;
        $participant->save();

        return back()->with('sukses', 'Password peserta direset.')
            ->with('password_baru', ['username' => $participant->username, 'password' => $password]);
    }

    public function verify(User $participant): RedirectResponse
    {
        $this->authorizeParticipant($participant);
        $this->guardSharedParticipant($participant);

        if (blank($participant->email)) {
            return back()->withErrors([
                'email' => 'Peserta belum punya email. Tambahkan email terlebih dahulu sebelum verifikasi.',
            ]);
        }

        if ($participant->hasVerifiedEmail()) {
            return back()->with('sukses', 'Email peserta sudah terverifikasi.');
        }

        $participant->forceFill(['email_verified_at' => now()])->save();
        AuditLog::record('participant_email_verified', 'user', $participant->id, [
            'name' => $participant->nama,
            'username' => $participant->username,
            'source' => 'admin',
        ]);

        return back()->with('sukses', 'Email peserta ditandai terverifikasi.');
    }

    public function destroy(User $participant): RedirectResponse
    {
        $this->authorizeParticipant($participant);
        $organizationId = app(OrganizationContext::class)->id();
        $classIds = $participant->kelas()->where('kelas.organization_id', $organizationId)->pluck('kelas.id');
        $participant->kelas()->detach($classIds);
        $participant->organizations()->detach($organizationId);
        AuditLog::record('participant_removed', 'user', $participant->id, [
            'name' => $participant->nama,
            'username' => $participant->username,
        ]);
        $pesan = 'Peserta dikeluarkan dari workspace. Akun, NIM, dan riwayat kuis tetap tersimpan. Hubungi master platform jika akun ini perlu ditautkan lagi atau namanya dikoreksi.';

        return redirect()->route('admin.participants.index')->with('sukses', $pesan);
    }

    public function importForm(): View
    {
        return view('admin.participants.import');
    }

    public function import(Request $request, OrganizationUsage $usage, ParticipantLinking $linking): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'file.required' => 'Pilih file CSV terlebih dahulu.',
            'file.mimes' => 'File harus berformat CSV.',
            'file.max' => 'Ukuran file maksimal 2 MB.',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'File tidak bisa dibaca.']);
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            throw ValidationException::withMessages(['file' => 'File CSV kosong.']);
        }

        $header = array_map(fn ($h) => Str::lower(trim((string) $h)), $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        $kolomNama = array_search('nama', $header, true);
        $kolomUsername = array_search('username', $header, true);

        if ($kolomNama === false || $kolomUsername === false) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => 'Header CSV harus memuat kolom nama dan username.',
            ]);
        }

        $kolomPassword = array_search('password', $header, true);

        $hasil = [];
        $usernameTerpakai = [];
        $baris = 1;
        $organization = app(OrganizationContext::class)->current();
        $organizationId = $organization?->id;
        $sisaKuota = $usage->participantRemaining($organizationId);

        while (($row = fgetcsv($handle)) !== false) {
            $baris++;

            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $nama = trim((string) ($row[$kolomNama] ?? ''));
            $username = trim((string) ($row[$kolomUsername] ?? ''));
            $password = $kolomPassword !== false ? trim((string) ($row[$kolomPassword] ?? '')) : '';
            $existing = null;

            $alasan = null;

            if ($nama === '' || mb_strlen($nama) > 120) {
                $alasan = 'Nama kosong atau lebih dari 120 karakter.';
            } elseif ($username === '' || ! preg_match('/^[A-Za-z0-9_-]{1,60}$/', $username)) {
                $alasan = 'Username kosong atau memakai karakter yang tidak diizinkan.';
            } elseif (isset($usernameTerpakai[Str::lower($username)])) {
                $alasan = 'Username duplikat di dalam file.';
            } else {
                $existing = $linking->findExisting($username);

                if ($existing !== null) {
                    if ($existing->trashed()) {
                        $alasan = 'NIM sudah dipakai akun yang dinonaktifkan. Hubungi master platform.';
                    } elseif (! $existing->isPeserta()) {
                        $alasan = 'Username sudah dipakai akun lain.';
                    } elseif (! $linking->namesMatch($existing, $nama)) {
                        $alasan = 'NIM/username sudah terdaftar dengan nama berbeda. Periksa ejaan nama atau minta master platform menautkan dengan konfirmasi nama berbeda.';
                    } elseif ($linking->isActiveMember($existing, $organizationId)) {
                        $alasan = 'Peserta sudah menjadi anggota workspace ini.';
                    } elseif ($sisaKuota <= 0) {
                        $alasan = 'Kuota peserta workspace penuh.';
                    }
                } elseif ($password !== '' && mb_strlen($password) < 8) {
                    $alasan = 'Password minimal 8 karakter.';
                } elseif ($sisaKuota <= 0) {
                    $alasan = 'Kuota peserta workspace penuh.';
                }
            }

            if ($alasan !== null) {
                $hasil[] = [
                    'baris' => $baris,
                    'nama' => $nama,
                    'username' => $username,
                    'status' => 'gagal',
                    'alasan' => $alasan,
                    'password' => null,
                ];

                continue;
            }

            $usernameTerpakai[Str::lower($username)] = true;

            if ($existing !== null) {
                $linking->link($existing, $organization, 'csv_import');
                $sisaKuota--;

                $alasanTautan = 'Akun existing ditautkan ke workspace ini. Password dan email tidak diubah.';
                if (blank($existing->email)) {
                    $alasanTautan .= ' Akun belum punya email.';
                } elseif (! $existing->hasVerifiedEmail()) {
                    $alasanTautan .= $existing->isSharedParticipant()
                        ? ' Email belum terverifikasi; minta master platform.'
                        : ' Email belum terverifikasi; verifikasi dari daftar peserta.';
                }

                $hasil[] = [
                    'baris' => $baris,
                    'nama' => $nama,
                    'username' => $username,
                    'status' => 'berhasil',
                    'alasan' => $alasanTautan,
                    'password' => null,
                ];

                continue;
            }

            $dibuat = null;

            if ($password === '') {
                $password = $this->buatPassword();
                $dibuat = $password;
            }

            $userId = DB::table('users')->insertGetId([
                'nama' => $nama,
                'username' => $username,
                'password' => bcrypt($password),
                'role' => 'peserta',
                'must_change_password' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('organization_memberships')->insert([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'role' => 'participant',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sisaKuota--;
            AuditLog::record('participant_added', 'user', $userId, [
                'name' => $nama,
                'username' => $username,
                'source' => 'csv_import',
            ]);

            $hasil[] = [
                'baris' => $baris,
                'nama' => $nama,
                'username' => $username,
                'status' => 'berhasil',
                'alasan' => null,
                'password' => $dibuat,
            ];
        }

        fclose($handle);

        $ringkasan = [
            'berhasil' => count(array_filter($hasil, fn ($h) => $h['status'] === 'berhasil')),
            'gagal' => count(array_filter($hasil, fn ($h) => $h['status'] === 'gagal')),
            'baris' => $hasil,
        ];

        $kredensial = array_values(array_filter($hasil, fn ($h) => $h['password'] !== null));

        $csv = "username,password\n";
        foreach ($kredensial as $k) {
            $csv .= $k['username'].','.$k['password']."\n";
        }

        $unduhan = $kredensial !== []
            ? 'data:text/csv;charset=utf-8;base64,'.base64_encode($csv)
            : null;

        return redirect()->route('admin.participants.import.result')->with('impor_hasil', [
            'ringkasan' => $ringkasan,
            'unduhan' => $unduhan,
        ]);
    }

    public function importResult(Request $request): View|RedirectResponse
    {
        $data = $request->session()->pull('impor_hasil');

        if ($data === null) {
            return redirect()->route('admin.participants.import');
        }

        return view('admin.participants.import-result', [
            'ringkasan' => $data['ringkasan'],
            'unduhan' => $data['unduhan'],
        ]);
    }

    public function template(): StreamedResponse
    {
        $isi = "nama,username,password\n"
            ."Budi Santoso,budi,\n"
            ."Siti Aminah,siti,rahasia123\n"
            ."Raka Pratama,raka,\n";

        return response()->streamDownload(function () use ($isi) {
            echo $isi;
        }, 'contoh-peserta.csv', ['Content-Type' => 'text/csv']);
    }

    private function buatPassword(): string
    {
        $panjang = strlen(self::ALPHABET);
        $password = '';

        for ($i = 0; $i < 8; $i++) {
            $password .= self::ALPHABET[random_int(0, $panjang - 1)];
        }

        return $password;
    }

    private function authorizeParticipant(User $participant): void
    {
        $organizationId = app(OrganizationContext::class)->id();
        abort_unless(
            $participant->role === 'peserta'
            && $organizationId !== null
            && $participant->organizations()->whereKey($organizationId)->wherePivot('status', 'active')->exists(),
            404,
        );
    }

    private function guardSharedParticipant(User $participant): void
    {
        abort_if(
            $participant->isSharedParticipant(),
            403,
            'Akun ini terhubung ke beberapa workspace. Perubahan identitas dan reset password tidak dapat dilakukan dari workspace. Hubungi master platform.',
        );
    }
}
