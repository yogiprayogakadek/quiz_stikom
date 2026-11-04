<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:160'],
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer', 'exists:questions,id'],
            'jumlah_soal' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('jumlah_soal') && count($this->input('question_ids', [])) > 0) {
                if ((int) $this->input('jumlah_soal') > count($this->input('question_ids', []))) {
                    $validator->errors()->add('jumlah_soal', 'Jumlah soal tidak boleh melebihi soal di pool.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'judul.required' => 'Judul kuis wajib diisi.',
            'question_ids.required' => 'Pilih minimal satu soal untuk pool kuis.',
            'question_ids.min' => 'Pilih minimal satu soal untuk pool kuis.',
        ];
    }
}
