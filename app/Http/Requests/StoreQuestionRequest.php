<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'teks' => ['required', 'string', 'max:2000'],
            'durasi_detik' => ['required', 'integer', 'min:5', 'max:300'],
            'pembahasan' => ['nullable', 'string', 'max:5000'],
            'opsi' => ['required', 'array', 'min:2', 'max:6'],
            'opsi.*.teks' => ['required', 'string', 'max:500'],
            'correct' => ['required', 'integer', 'min:0', 'max:5'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $jumlah = count($this->input('opsi', []));
            $correct = (int) $this->input('correct', -1);

            if ($correct < 0 || $correct >= $jumlah) {
                $validator->errors()->add('correct', 'Pilih tepat satu jawaban benar.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'teks.required' => 'Teks soal wajib diisi.',
            'durasi_detik.required' => 'Durasi soal wajib diisi.',
            'durasi_detik.min' => 'Durasi minimal 5 detik.',
            'durasi_detik.max' => 'Durasi maksimal 300 detik.',
            'opsi.min' => 'Soal butuh minimal 2 opsi.',
            'opsi.max' => 'Soal maksimal 6 opsi.',
            'opsi.*.teks.required' => 'Setiap opsi wajib berisi teks.',
        ];
    }
}
