<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatatanPengajarTest extends TestCase
{
    use RefreshDatabase;

    public function test_catatan_pengajar_tersimpan_dan_terbaca(): void
    {
        [, , $answer] = $this->jawabanEssay();

        $answer->update(['catatan_pengajar' => 'Struktur argumen sudah tepat.']);

        $this->assertSame('Struktur argumen sudah tepat.', $answer->fresh()->catatan_pengajar);
    }

    /**
     * Attempt selesai mode acak dengan satu soal essay yang sudah dijawab.
     *
     * @return array{0: User, 1: Attempt, 2: Answer}
     */
    private function jawabanEssay(array $sessionOverrides = []): array
    {
        $admin = User::create([
            'nama' => 'Admin Catatan',
            'username' => 'admin-catatan',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-catatan-admin',
        ]);

        $peserta = User::create([
            'nama' => 'Peserta Catatan',
            'username' => 'peserta-catatan',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
            'current_session_token' => 'token-catatan-peserta',
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Catatan',
            'created_by' => $admin->id,
        ]);

        $essay = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Jelaskan peran guru dalam penilaian.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
            'kunci_essay' => 'Guru menilai proses dan hasil.',
        ]);

        $session = QuizSession::create(array_merge([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Catatan',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'token-catatan',
            'created_by' => $admin->id,
        ], $sessionOverrides));

        $attempt = Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'selesai',
            'mulai_at' => now()->getTimestampMs(),
            'selesai_at' => now()->getTimestampMs(),
            'bergabung_at' => now(),
        ]);

        AttemptQuestion::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'urutan' => 1,
            'urutan_opsi_json' => [],
        ]);

        $answer = Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'jawaban_teks' => 'Guru menilai proses belajar dan hasil akhir.',
            'jawaban_parts_json' => [],
            'waktu_jawab_ms' => 2000,
            'skor' => 0,
            'answered_at' => now(),
        ]);

        return [$admin, $attempt, $answer];
    }
}
