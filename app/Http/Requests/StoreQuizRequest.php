<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'judul' => ['required', 'string', 'max:160'],
            'jumlah_soal' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $quiz = $this->route('quiz');
            if ($this->filled('jumlah_soal') && $quiz) {
                $totalSoal = $quiz->questions()->count();
                if ($totalSoal > 0 && (int) $this->input('jumlah_soal') > $totalSoal) {
                    $validator->errors()->add('jumlah_soal', 'Jumlah soal tidak boleh melebihi total soal kuis (' . $totalSoal . ').');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'kelas_id.required' => 'Pilih kelas untuk kuis ini.',
            'kelas_id.exists' => 'Kelas yang dipilih tidak ditemukan.',
            'judul.required' => 'Judul kuis wajib diisi.',
            'judul.max' => 'Judul kuis maksimal 160 karakter.',
            'jumlah_soal.integer' => 'Jumlah soal harus berupa angka.',
            'jumlah_soal.min' => 'Jumlah soal minimal 1.',
        ];
    }
}
