<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'teks' => ['required', 'string', 'max:2000'],
            'tipe' => ['required', Rule::in(['pilihan_ganda', 'essay'])],
            'bobot' => ['required', 'integer', 'min:1', 'max:1000'],
            'durasi_detik' => ['required', 'integer', 'min:5', 'max:300'],
            'pembahasan' => ['nullable', 'string', 'max:5000'],
            'kunci_essay' => ['nullable', 'string', 'max:5000'],
            'rubrik_essay' => ['nullable', 'array', 'max:10'],
            'rubrik_essay.*.label' => ['required', 'string', 'max:200'],
            'rubrik_essay.*.points' => ['required', 'integer', 'min:1', 'max:1000'],
            'opsi' => ['required_if:tipe,pilihan_ganda', 'array', 'min:2', 'max:6'],
            'opsi.*.teks' => ['required_if:tipe,pilihan_ganda', 'string', 'max:500'],
            'correct' => ['required_if:tipe,pilihan_ganda', 'integer', 'min:0', 'max:5'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('tipe') === 'essay') {
                $rubric = $this->input('rubrik_essay', []);
                if ($rubric !== [] && collect($rubric)->sum(fn ($criterion) => (int) ($criterion['points'] ?? 0)) !== (int) $this->input('bobot')) {
                    $validator->errors()->add('rubrik_essay', 'Total poin kriteria harus sama dengan nilai maksimal essay.');
                }

                return;
            }

            if ($this->input('tipe') !== 'pilihan_ganda') {
                return;
            }

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
            'tipe.required' => 'Tipe soal wajib dipilih.',
            'tipe.in' => 'Tipe soal tidak dikenali.',
            'bobot.required' => 'Bobot soal wajib diisi.',
            'bobot.min' => 'Bobot minimal 1.',
            'bobot.max' => 'Bobot maksimal 1000.',
            'durasi_detik.required' => 'Durasi soal wajib diisi.',
            'durasi_detik.min' => 'Durasi minimal 5 detik.',
            'durasi_detik.max' => 'Durasi maksimal 300 detik.',
            'opsi.min' => 'Soal butuh minimal 2 opsi.',
            'opsi.max' => 'Soal maksimal 6 opsi.',
            'opsi.*.teks.required' => 'Setiap opsi wajib berisi teks.',
            'rubrik_essay.max' => 'Rubrik maksimal memiliki 10 kriteria.',
            'rubrik_essay.*.label.required' => 'Nama setiap kriteria wajib diisi.',
            'rubrik_essay.*.points.required' => 'Poin setiap kriteria wajib diisi.',
        ];
    }
}
