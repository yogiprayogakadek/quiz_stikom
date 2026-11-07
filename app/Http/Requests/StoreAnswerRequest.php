<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnswerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'option_id' => ['nullable', 'integer', 'exists:options,id'],
            'jawaban_teks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'question_id.required' => 'Soal tidak dikenali.',
            'jawaban_teks.max' => 'Jawaban essay maksimal 5000 karakter.',
        ];
    }
}
