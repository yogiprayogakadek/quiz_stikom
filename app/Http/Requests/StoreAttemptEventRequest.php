<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttemptEventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tipe' => ['required', 'in:blur_window,tab_hidden,fullscreen_exit,fullscreen_exit_timeout,copy_attempt,kembali'],
            'detail' => ['nullable', 'string', 'max:500'],
            'durasi_detik' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipe.in' => 'Jenis kejadian tidak dikenali.',
        ];
    }
}
