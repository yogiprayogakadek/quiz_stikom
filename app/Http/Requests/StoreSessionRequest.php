<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'quiz_id' => ['required', 'integer', 'exists:quizzes,id'],
            'nama' => ['required', 'string', 'max:160'],
            'mode' => ['required', 'in:acak,serempak'],
            'acak_opsi' => ['boolean'],
            'tampilkan_pembahasan' => ['boolean'],
            'leaderboard_live' => ['boolean'],
            'max_peserta' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'jeda_detik' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'quiz_id.required' => 'Pilih kuis untuk sesi ini.',
            'nama.required' => 'Nama sesi wajib diisi.',
            'mode.required' => 'Pilih mode sesi.',
            'jeda_detik.min' => 'Jeda minimal 1 detik.',
            'jeda_detik.max' => 'Jeda maksimal 60 detik.',
        ];
    }
}
