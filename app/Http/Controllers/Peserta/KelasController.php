<?php

namespace App\Http\Controllers\Peserta;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KelasController extends Controller
{
    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'max:12'],
        ], [
            'pin.required' => 'Masukkan PIN kelas dari admin.',
        ]);

        $kelas = Kelas::where('pin', $data['pin'])->first();

        if ($kelas === null) {
            throw ValidationException::withMessages([
                'pin' => 'PIN kelas tidak ditemukan. Periksa lagi PIN dari admin.',
            ]);
        }

        $kelas->peserta()->syncWithoutDetaching([$request->user()->id]);

        return back()->with('sukses', 'Kamu bergabung ke kelas '.$kelas->nama.'.');
    }
}
