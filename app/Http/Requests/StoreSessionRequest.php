<?php

namespace App\Http\Requests;

use App\Models\Question;
use App\Models\Quiz;
use App\Support\ExamTools;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'quiz_id' => ['required', 'integer', Rule::exists('quizzes', 'id')
                ->where('organization_id', app(OrganizationContext::class)->id())],
            'kelas_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')
                ->where('organization_id', app(OrganizationContext::class)->id())],
            'nama' => ['required', 'string', 'max:160'],
            'mode' => ['required', 'in:acak,serempak'],
            'acak_opsi' => ['boolean'],
            'tampilkan_pembahasan' => ['boolean'],
            'pembahasan_mode' => ['nullable', 'in:tersembunyi,otomatis,terbuka'],
            'leaderboard_live' => ['boolean'],
            'max_peserta' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'max_pelanggaran' => ['nullable', 'integer', 'min:1', 'max:100'],
            'durasi_peringatan' => ['nullable', 'integer', 'min:5', 'max:300'],
            'pelanggaran_action' => ['nullable', 'in:submit,reduce_time'],
            'pelanggaran_penalty_seconds' => ['nullable', 'integer', 'min:1', 'max:36000'],
            'clipboard_policy' => ['nullable', 'in:allow,monitor,block'],
            'exam_tools_present' => ['nullable', 'boolean'],
            'exam_tools' => ['nullable', 'array'],
            'exam_tools.*' => ['string', 'distinct', Rule::in(ExamTools::keys())],
            'jeda_detik' => ['required', 'integer', 'min:1', 'max:60'],
            'durasi_total_menit' => ['nullable', 'integer', 'min:1', 'max:600'],
            'timing_mode' => ['nullable', 'in:duration,scheduled'],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $quizId = (int) $this->input('quiz_id');

            if ($quizId === 0) {
                return;
            }

            $punyaEssay = Question::query()
                ->where('quiz_id', $quizId)
                ->where('tipe', 'essay')
                ->exists();

            if ($punyaEssay && $this->input('mode') === 'serempak') {
                $validator->errors()->add('mode', 'Kuis yang memuat soal essay hanya dapat dijalankan pada Mode Acak.');
            }

            $quiz = Quiz::query()->find($quizId);
            if ($quiz && ! $quiz->is_percobaan
                && in_array(ExamTools::SUBNET_CALCULATOR, (array) $this->input('exam_tools', []), true)) {
                $validator->errors()->add('exam_tools', 'Kalkulator subnet hanya boleh diaktifkan pada sesi simulasi.');
            }

            if ($this->input('pelanggaran_action') === 'reduce_time') {
                if (! $this->filled('max_pelanggaran')) {
                    $validator->errors()->add('max_pelanggaran', 'Isi batas pelanggaran untuk memakai pengurangan waktu.');
                }

                if (! $this->filled('pelanggaran_penalty_seconds')) {
                    $validator->errors()->add('pelanggaran_penalty_seconds', 'Isi jumlah detik yang dikurangi.');
                }
            }

            if ($this->input('timing_mode') === 'scheduled') {
                if (! $this->filled('scheduled_start_at')) {
                    $validator->errors()->add('scheduled_start_at', 'Isi jam mulai server.');
                }

                if (! $this->filled('scheduled_end_at')) {
                    $validator->errors()->add('scheduled_end_at', 'Isi jam selesai server.');
                }

                if ($this->filled('scheduled_start_at')
                    && $this->filled('scheduled_end_at')
                    && ! $validator->errors()->has('scheduled_start_at')
                    && ! $validator->errors()->has('scheduled_end_at')) {
                    $timezone = app(OrganizationContext::class)->timezone();
                    $start = Carbon::parse($this->input('scheduled_start_at'), $timezone);
                    $end = Carbon::parse($this->input('scheduled_end_at'), $timezone);

                    if ($end->lessThanOrEqualTo($start)) {
                        $validator->errors()->add('scheduled_end_at', 'Jam selesai harus setelah jam mulai.');
                    } elseif ($end->lessThanOrEqualTo(now($timezone))) {
                        $validator->errors()->add('scheduled_end_at', 'Jam selesai harus berada di masa depan.');
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'quiz_id.required' => 'Pilih kuis untuk sesi ini.',
            'nama.required' => 'Nama sesi wajib diisi.',
            'mode.required' => 'Pilih mode sesi.',
            'max_pelanggaran.min' => 'Batas pelanggaran minimal 1.',
            'max_pelanggaran.max' => 'Batas pelanggaran maksimal 100.',
            'durasi_peringatan.min' => 'Durasi peringatan minimal 5 detik.',
            'durasi_peringatan.max' => 'Durasi peringatan maksimal 300 detik.',
            'pelanggaran_action.required' => 'Pilih sanksi pelanggaran.',
            'pelanggaran_action.in' => 'Sanksi pelanggaran tidak valid.',
            'pelanggaran_penalty_seconds.min' => 'Pengurangan waktu minimal 1 detik.',
            'pelanggaran_penalty_seconds.max' => 'Pengurangan waktu maksimal 36000 detik.',
            'clipboard_policy.in' => 'Kebijakan copy dan paste tidak valid.',
            'exam_tools.array' => 'Daftar alat bantu tidak valid.',
            'exam_tools.*.in' => 'Alat bantu yang dipilih tidak tersedia.',
            'exam_tools.*.distinct' => 'Alat bantu tidak boleh dipilih lebih dari sekali.',
            'jeda_detik.min' => 'Jeda minimal 1 detik.',
            'jeda_detik.max' => 'Jeda maksimal 60 detik.',
            'durasi_total_menit.min' => 'Durasi total minimal 1 menit.',
            'durasi_total_menit.max' => 'Durasi total maksimal 600 menit.',
            'timing_mode.required' => 'Pilih cara menentukan waktu ujian.',
            'timing_mode.in' => 'Mode waktu tidak valid.',
            'scheduled_start_at.date' => 'Jam mulai tidak valid.',
            'scheduled_end_at.date' => 'Jam selesai tidak valid.',
        ];
    }
}
