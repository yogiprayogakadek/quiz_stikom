<?php

namespace App\Http\Controllers\Peserta;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KelasController extends Controller
{
    public function index(Request $request): View
    {
        $kelas = Kelas::withCount(['quizzes', 'sessions'])->orderBy('nama')->get();
        $diikuti = $request->user()->kelas()->pluck('kelas.id')->map(fn ($id) => (int) $id)->all();

        return view('peserta.kelas.index', compact('kelas', 'diikuti'));
    }

    public function show(Request $request, Kelas $kelas): View
    {
        $user = $request->user();
        $anggota = $kelas->peserta()->whereKey($user->id)->exists();

        $sesi = collect();

        if ($anggota) {
            $kelas->load(['quizzes' => fn ($q) => $q->withCount('questions')]);

            $sesi = $kelas->sessions()
                ->with(['quiz', 'attempts' => fn ($q) => $q->where('user_id', $user->id)])
                ->withCount('attempts')
                ->whereIn('status', ['lobby', 'berjalan'])
                ->orderByRaw("CASE status WHEN 'berjalan' THEN 0 ELSE 1 END")
                ->orderByDesc('dibuka_at')
                ->get();
        }

        return view('peserta.kelas.show', compact('kelas', 'anggota', 'sesi'));
    }

    public function masuk(Request $request, Kelas $kelas): RedirectResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'max:12'],
        ], [
            'pin.required' => 'Masukkan PIN kelas.',
        ]);

        if (! hash_equals((string) $kelas->pin, (string) $data['pin'])) {
            throw ValidationException::withMessages([
                'pin' => 'PIN kelas salah. Periksa lagi PIN dari admin.',
            ]);
        }

        $kelas->peserta()->syncWithoutDetaching([$request->user()->id]);

        return redirect()->route('peserta.kelas.show', $kelas)
            ->with('sukses', 'Kamu masuk ke kelas '.$kelas->nama.'.');
    }
}
