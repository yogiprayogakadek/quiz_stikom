<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttemptEventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tipe' => ['required', 'in:blur_window,tab_hidden,fullscreen_exit,fullscreen_exit_timeout,copy_attempt'],
            'detail' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipe.in' => 'Jenis kejadian tidak dikenali.',
        ];
    }
}
