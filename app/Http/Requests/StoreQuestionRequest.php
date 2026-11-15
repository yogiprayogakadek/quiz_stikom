<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('sub_soal'))) {
            return;
        }

        $this->merge([
            'sub_soal' => collect($this->input('sub_soal'))
                ->map(function ($item, $index) {
                    if (! is_array($item)) {
                        return $item;
                    }

                    $item['key'] = filled($item['key'] ?? null) ? $item['key'] : 'legacy-'.$index;
                    $item['parent_key'] = filled($item['parent_key'] ?? null) ? $item['parent_key'] : null;

                    return $item;
                })
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'teks' => ['required', 'string', 'max:2000'],
            'tipe' => ['required', Rule::in(['pilihan_ganda', 'essay'])],
            'bobot' => ['required', 'integer', 'min:1', 'max:1000'],
            'durasi_detik' => ['nullable', 'integer', 'min:5', 'max:300', 'required_if:tipe,pilihan_ganda'],
            'pembahasan' => ['nullable', 'string', 'max:5000'],
            'kunci_essay' => ['nullable', 'string', 'max:5000'],
            'rubrik_essay' => ['nullable', 'array', 'max:10'],
            'rubrik_essay.*.label' => ['required', 'string', 'max:200'],
            'rubrik_essay.*.points' => ['required', 'integer', 'min:1', 'max:1000'],
            'petunjuk' => ['nullable', 'string', 'max:5000'],
            'sub_soal' => ['nullable', 'array', 'max:50'],
            'sub_soal.*.key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct'],
            'sub_soal.*.parent_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'sub_soal.*.label' => ['required', 'string', 'max:10'],
            'sub_soal.*.teks' => ['required', 'string', 'max:2000'],
            'sub_soal.*.petunjuk' => ['nullable', 'string', 'max:5000'],
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

                $this->validateSubQuestions($validator);

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
            'sub_soal.max' => 'Sub-soal maksimal 50 bagian.',
            'sub_soal.*.key.required' => 'Identitas setiap sub-soal wajib tersedia.',
            'sub_soal.*.key.distinct' => 'Identitas sub-soal tidak boleh sama.',
            'sub_soal.*.label.required' => 'Label setiap sub-soal wajib diisi.',
            'sub_soal.*.teks.required' => 'Teks setiap sub-soal wajib diisi.',
        ];
    }

    private function validateSubQuestions($validator): void
    {
        $items = collect($this->input('sub_soal', []))
            ->filter(fn ($item) => is_array($item))
            ->values();
        $byKey = $items->keyBy(fn (array $item) => (string) ($item['key'] ?? ''));

        foreach ($items as $index => $item) {
            $key = (string) ($item['key'] ?? '');
            $parentKey = trim((string) ($item['parent_key'] ?? ''));

            if ($parentKey !== '' && ! $byKey->has($parentKey)) {
                $validator->errors()->add("sub_soal.{$index}.parent_key", 'Induk sub-soal tidak ditemukan.');

                continue;
            }

            $visited = [$key => true];
            while ($parentKey !== '') {
                if (isset($visited[$parentKey])) {
                    $validator->errors()->add("sub_soal.{$index}.parent_key", 'Hubungan sub-soal tidak boleh membentuk lingkaran.');
                    break;
                }

                $visited[$parentKey] = true;
                $parentKey = trim((string) ($byKey->get($parentKey)['parent_key'] ?? ''));
            }
        }

        $items->groupBy(fn (array $item) => filled($item['parent_key'] ?? null) ? $item['parent_key'] : '__root__')
            ->each(function ($siblings) use ($validator) {
                if ($siblings->count() > 10) {
                    $validator->errors()->add('sub_soal', 'Setiap bagian maksimal memiliki 10 sub-soal langsung.');
                }

                $labels = $siblings->map(fn (array $item) => mb_strtolower(trim((string) ($item['label'] ?? ''))));
                if ($labels->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('sub_soal', 'Label sub-soal dalam induk yang sama tidak boleh sama.');
                }
            });
    }
}
