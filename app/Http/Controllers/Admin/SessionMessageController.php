<?php

namespace App\Http\Controllers\Admin;

use App\Events\PesanPengajar;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\QuizSession;
use App\Models\SessionMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SessionMessageController extends Controller
{
    public function store(Request $request, QuizSession $session): RedirectResponse
    {
        if ($session->status !== 'berjalan') {
            throw ValidationException::withMessages(['teks' => 'Pesan hanya bisa dikirim saat sesi berjalan.']);
        }

        $data = $request->validate([
            'teks' => ['required', 'string', 'max:500'],
            'target' => ['required', 'in:semua,terpilih'],
            'attempt_ids' => ['required_if:target,terpilih', 'array', 'min:1'],
            'attempt_ids.*' => ['integer'],
        ], [
            'teks.required' => 'Tulis pesan terlebih dahulu.',
            'teks.max' => 'Pesan maksimal 500 karakter.',
            'target.required' => 'Pilih penerima pesan.',
            'target.in' => 'Pilihan penerima pesan tidak valid.',
            'attempt_ids.required_if' => 'Pilih minimal satu peserta.',
            'attempt_ids.min' => 'Pilih minimal satu peserta.',
        ]);

        $penerima = $session->attempts()
            ->where('status', 'berjalan')
            ->when($data['target'] === 'terpilih', fn ($query) => $query->whereIn(
                'id',
                array_map('intval', $data['attempt_ids'] ?? []),
            ))
            ->get();

        if ($penerima->isEmpty()) {
            throw ValidationException::withMessages(['attempt_ids' => 'Tidak ada peserta yang sedang mengerjakan.']);
        }

        $teks = trim($data['teks']);

        $pesan = DB::transaction(fn () => $penerima->map(fn (Attempt $attempt) => SessionMessage::create([
            'quiz_session_id' => $session->id,
            'attempt_id' => $attempt->id,
            'user_id' => $attempt->user_id,
            'pengirim_id' => $request->user()->id,
            'teks' => $teks,
            'tipe' => 'info',
        ])));

        foreach ($pesan as $item) {
            PesanPengajar::safeDispatch($session->id, $item->user_id, $item->untukPeserta());
        }

        return back()->with('sukses', "Pesan terkirim ke {$pesan->count()} peserta.");
    }
}
