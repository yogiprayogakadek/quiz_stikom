<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParticipantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama peserta wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah dipakai.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, garis bawah, dan tanda hubung.',
            'email.required' => 'Email wajib diisi untuk akun baru.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai akun lain.',
            'password.min' => 'Password minimal 8 karakter.',
        ];
    }
}
