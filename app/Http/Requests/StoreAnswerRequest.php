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
            'jawaban_teks' => ['nullable', 'string', 'max:20000'],
            'jawaban_table' => ['nullable', 'array'],
            'jawaban_table.cells' => ['nullable', 'array', 'max:20'],
            'jawaban_table.cells.*' => ['array', 'max:10'],
            'jawaban_table.cells.*.*' => ['nullable', 'string', 'max:1000'],
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
            'jawaban_teks.max' => 'Jawaban essay maksimal 20.000 karakter.',
            'jawaban_table.array' => 'Jawaban tabel tidak valid.',
            'jawaban_table.cells.max' => 'Jawaban tabel maksimal memiliki 20 baris.',
            'jawaban_table.cells.*.max' => 'Jawaban tabel maksimal memiliki 10 kolom.',
            'jawaban_table.cells.*.*.max' => 'Isi setiap sel maksimal 1.000 karakter.',
            'part_key.string' => 'Bagian jawaban tidak valid.',
            'part_key.max' => 'Kode bagian jawaban maksimal 64 karakter.',
        ];
    }
}
