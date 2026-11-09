<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateClassCoverJob;
use App\Models\Kelas;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KelasController extends Controller
{
    public function index(): View
    {
        $kelas = Kelas::withCount(['peserta', 'quizzes', 'sessions'])
            ->orderBy('nama')
            ->get();

        return view('admin.classes.index', compact('kelas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'pin' => ['required', 'string', 'min:4', 'max:12', 'alpha_num', 'unique:kelas,pin'],
        ], [
            'nama.required' => 'Nama kelas wajib diisi.',
            'pin.required' => 'PIN kelas wajib diisi.',
            'pin.min' => 'PIN minimal 4 karakter.',
            'pin.max' => 'PIN maksimal 12 karakter.',
            'pin.alpha_num' => 'PIN hanya boleh huruf dan angka.',
            'pin.unique' => 'PIN ini sudah dipakai kelas lain.',
        ]);

        $kelas = Kelas::create($data + ['created_by' => $request->user()->id]);

        if (app('app.settings')->class_cover_mode === 'ai') {
            GenerateClassCoverJob::dispatch($kelas->id);
        }

        return redirect()->route('admin.classes.edit', $kelas)
            ->with('sukses', 'Kelas dibuat. Tambahkan peserta dan kuis di halaman ini.');
    }

    public function edit(Kelas $kelas): View
    {
        $kelas->load([
            'peserta' => fn ($q) => $q->orderBy('nama'),
            'quizzes' => fn ($q) => $q->orderBy('judul'),
            'sessions.quiz',
        ]);

        $peserta = User::where('role', 'peserta')->orderBy('nama')->get();
        $quizzes = Quiz::orderBy('judul')->get();

        return view('admin.classes.edit', compact('kelas', 'peserta', 'quizzes'));
    }

    public function update(Request $request, Kelas $kelas): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'pin' => ['required', 'string', 'min:4', 'max:12', 'alpha_num', Rule::unique('kelas', 'pin')->ignore($kelas->id)],
            'peserta_ids' => ['nullable', 'array'],
            'peserta_ids.*' => ['integer', 'exists:users,id'],
            'quiz_ids' => ['nullable', 'array'],
            'quiz_ids.*' => ['integer', 'exists:quizzes,id'],
        ], [
            'nama.required' => 'Nama kelas wajib diisi.',
            'pin.required' => 'PIN kelas wajib diisi.',
            'pin.min' => 'PIN minimal 4 karakter.',
            'pin.max' => 'PIN maksimal 12 karakter.',
            'pin.alpha_num' => 'PIN hanya boleh huruf dan angka.',
            'pin.unique' => 'PIN ini sudah dipakai kelas lain.',
        ]);

        DB::transaction(function () use ($kelas, $data) {
            $kelas->update(['nama' => $data['nama'], 'pin' => $data['pin']]);
            $kelas->peserta()->sync($data['peserta_ids'] ?? []);

            $selectedQuizIds = $data['quiz_ids'] ?? [];
            if (! empty($selectedQuizIds)) {
                Quiz::whereIn('id', $selectedQuizIds)->update(['kelas_id' => $kelas->id]);
            }
        });

        return back()->with('sukses', 'Kelas diperbarui.');
    }

    public function destroy(Kelas $kelas): RedirectResponse
    {
        if ($kelas->sessions()->exists()) {
            throw ValidationException::withMessages([
                'kelas' => 'Kelas masih punya sesi. Hapus sesinya dulu sebelum menghapus kelas.',
            ]);
        }

        $kelas->delete();

        return redirect()->route('admin.classes.index')->with('sukses', 'Kelas dihapus.');
    }

    public function regenerateCover(Kelas $kelas): RedirectResponse
    {
        if (app('app.settings')->class_cover_mode !== 'ai') {
            return back()->withErrors(['cover' => 'Aktifkan mode cover AI di Pengaturan terlebih dahulu.']);
        }

        GenerateClassCoverJob::dispatch($kelas->id);

        return back()->with('sukses', 'Cover AI sedang dibuat. Cover saat ini tetap dipakai sampai proses selesai.');
    }
}
