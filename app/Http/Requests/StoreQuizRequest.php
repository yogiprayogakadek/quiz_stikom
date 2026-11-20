<?php

namespace App\Http\Requests;

use App\Support\ExamTools;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuizRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'kelas_id' => ['required', 'integer', Rule::exists('kelas', 'id')
                ->where('organization_id', app(OrganizationContext::class)->id())],
            'judul' => ['required', 'string', 'max:160'],
            'jumlah_soal' => ['nullable', 'integer', 'min:1'],
            'acak_opsi' => ['nullable', 'boolean'],
            'auto_grade_essay' => ['nullable', 'boolean'],
            'is_percobaan' => ['nullable', 'boolean'],
            'exam_tools' => ['nullable', 'array'],
            'exam_tools.*' => ['string', 'distinct', Rule::in(ExamTools::keys())],
            'remidi_nilai_min' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $quiz = $this->route('quiz');
            if ($this->filled('jumlah_soal') && $quiz) {
                $totalSoal = $quiz->questions()->count();
                if ($totalSoal > 0 && (int) $this->input('jumlah_soal') > $totalSoal) {
                    $validator->errors()->add('jumlah_soal', 'Jumlah soal tidak boleh melebihi total soal kuis ('.$totalSoal.').');
                }
            }

            if (! $this->boolean('is_percobaan')
                && in_array(ExamTools::SUBNET_CALCULATOR, (array) $this->input('exam_tools', []), true)) {
                $validator->errors()->add('exam_tools', 'Kalkulator subnet hanya boleh diaktifkan pada kuis simulasi.');
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
            'exam_tools.array' => 'Daftar alat bantu tidak valid.',
            'exam_tools.*.in' => 'Alat bantu yang dipilih tidak tersedia.',
            'exam_tools.*.distinct' => 'Alat bantu tidak boleh dipilih lebih dari sekali.',
        ];
    }
}
