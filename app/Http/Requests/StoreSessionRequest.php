<?php

namespace App\Http\Requests;

use App\Support\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'quiz_id' => ['required', 'integer', Rule::exists('quizzes', 'id')
                ->where('organization_id', app(OrganizationContext::class)->id())],
            'kelas_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')
                ->where('organization_id', app(OrganizationContext::class)->id())],
            'nama' => ['required', 'string', 'max:160'],
            'mode' => ['required', 'in:acak,serempak'],
            'acak_opsi' => ['boolean'],
            'tampilkan_pembahasan' => ['boolean'],
            'pembahasan_mode' => ['nullable', 'in:tersembunyi,otomatis,terbuka'],
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
