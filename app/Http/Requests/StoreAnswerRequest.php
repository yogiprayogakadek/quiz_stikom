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
            'part_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'question_id.required' => 'Soal tidak dikenali.',
            'question_id.integer' => 'Soal tidak dikenali.',
            'question_id.exists' => 'Soal tidak ditemukan.',
            'option_id.integer' => 'Pilihan jawaban tidak valid.',
            'option_id.exists' => 'Pilihan jawaban tidak ditemukan.',
            'jawaban_teks.string' => 'Jawaban essay harus berupa teks.',
            'jawaban_teks.max' => 'Jawaban essay maksimal 5000 karakter.',
            'part_key.string' => 'Bagian jawaban tidak valid.',
            'part_key.max' => 'Kode bagian jawaban maksimal 64 karakter.',
        ];
    }
}
