<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnswerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'option_id' => ['required', 'integer', 'exists:options,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'question_id.required' => 'Soal tidak dikenali.',
            'option_id.required' => 'Pilih salah satu jawaban.',
        ];
    }
}
