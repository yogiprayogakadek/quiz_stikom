<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreParticipantRequest;
use App\Http\Requests\UpdateParticipantRequest;
use App\Models\User;
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
        $cari = trim((string) $request->query('q'));

        // shortcut: client-side pagination up to 2000 rows for real-time responsiveness, upgrade to AJAX datatable if huge
        $participants = User::where('role', 'peserta')
            ->when($cari !== '', function ($query) use ($cari) {
                $query->where(fn ($q) => $q
                    ->where('nama', 'like', "%{$cari}%")
                    ->orWhere('username', 'like', "%{$cari}%"));
            })
            ->withCount('attempts')
            ->orderBy('nama')
            ->get();

        return view('admin.participants.index', compact('participants', 'cari'));
    }

    public function create(): View
    {
        return view('admin.participants.create');
    }

    public function store(StoreParticipantRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $password = $data['password'] ?? null;
        $dibuat = null;

        if (blank($password)) {
            $password = $this->buatPassword();
            $dibuat = $password;
        }

        User::create([
            'nama' => $data['nama'],
            'username' => $data['username'],
            'password' => $password,
            'role' => 'peserta',
            'must_change_password' => true,
        ]);

        return redirect()
            ->route('admin.participants.index')
            ->with('sukses', 'Peserta '.$data['nama'].' ditambahkan.')
            ->with('password_baru', $dibuat !== null ? ['username' => $data['username'], 'password' => $dibuat] : null);
    }

    public function edit(User $participant): View
    {
        abort_unless($participant->role === 'peserta', 404);

        return view('admin.participants.edit', compact('participant'));
    }

    public function update(UpdateParticipantRequest $request, User $participant): RedirectResponse
    {
        abort_unless($participant->role === 'peserta', 404);

        $data = $request->validated();

        $participant->nama = $data['nama'];
        $participant->username = $data['username'];

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
        abort_unless($participant->role === 'peserta', 404);

        $password = $this->buatPassword();
        $participant->password = $password;
        $participant->must_change_password = true;
        $participant->current_session_token = null;
        $participant->save();

        return back()->with('sukses', 'Password peserta direset.')
            ->with('password_baru', ['username' => $participant->username, 'password' => $password]);
    }

    public function destroy(User $participant): RedirectResponse
    {
        abort_unless($participant->role === 'peserta', 404);

        $punyaAttempt = $participant->attempts()->exists();

        if ($punyaAttempt) {
            $participant->delete();
            $pesan = 'Peserta dihapus (data attempt tetap disimpan).';
        } else {
            $participant->forceDelete();
            $pesan = 'Peserta dihapus permanen.';
        }

        return redirect()->route('admin.participants.index')->with('sukses', $pesan);
    }

    public function importForm(): View
    {
        return view('admin.participants.import');
    }

    public function import(Request $request): RedirectResponse
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

        while (($row = fgetcsv($handle)) !== false) {
            $baris++;

            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $nama = trim((string) ($row[$kolomNama] ?? ''));
            $username = trim((string) ($row[$kolomUsername] ?? ''));
            $password = $kolomPassword !== false ? trim((string) ($row[$kolomPassword] ?? '')) : '';

            $alasan = null;

            if ($nama === '' || mb_strlen($nama) > 120) {
                $alasan = 'Nama kosong atau lebih dari 120 karakter.';
            } elseif ($username === '' || ! preg_match('/^[A-Za-z0-9_-]{1,60}$/', $username)) {
                $alasan = 'Username kosong atau memakai karakter yang tidak diizinkan.';
            } elseif (isset($usernameTerpakai[Str::lower($username)])) {
                $alasan = 'Username duplikat di dalam file.';
            } elseif (User::withTrashed()->where('username', $username)->exists()) {
                $alasan = 'Username sudah dipakai.';
            } elseif ($password !== '' && mb_strlen($password) < 6) {
                $alasan = 'Password minimal 6 karakter.';
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
            $dibuat = null;

            if ($password === '') {
                $password = $this->buatPassword();
                $dibuat = $password;
            }

            DB::table('users')->insert([
                'nama' => $nama,
                'username' => $username,
                'password' => bcrypt($password),
                'role' => 'peserta',
                'must_change_password' => true,
                'created_at' => now(),
                'updated_at' => now(),
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
}
