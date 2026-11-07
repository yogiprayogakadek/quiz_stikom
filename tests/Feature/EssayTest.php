<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EssayTest extends TestCase
{
    use RefreshDatabase;

    public function test_skor_acak_memakai_bobot_dan_essay_dinilai_manual(): void
    {
        [$admin, $peserta, $session, $attempt, $pg, $essay, $benar] = $this->buatAcakBerjalan();

        app(QuizEngine::class)->selesaikanAcak($attempt);

        $attempt->refresh();
        $this->assertSame('selesai', $attempt->status);
        $this->assertSame(1, $attempt->jumlah_benar);
        $this->assertSame(2, $attempt->poin_total);
        $this->assertSame(12, $attempt->poin_maksimal);
        $this->assertSame(17, $attempt->skor_total);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$essay->id => 8],
            ])
            ->assertRedirect();

        $attempt->refresh();
        $this->assertSame(10, $attempt->poin_total);
        $this->assertSame(12, $attempt->poin_maksimal);
        $this->assertSame(83, $attempt->skor_total);

        $jawabanEssay = Answer::where('attempt_id', $attempt->id)
            ->where('question_id', $essay->id)
            ->first();

        $this->assertNotNull($jawabanEssay->dinilai_at);
        $this->assertSame(8, $jawabanEssay->skor);
        $this->assertSame($admin->id, $jawabanEssay->dinilai_oleh);
    }

    public function test_peserta_bisa_menyimpan_dan_menghapus_jawaban_essay(): void
    {
        [$admin, $peserta, $session, $attempt, $pg, $essay] = $this->buatAcakBerjalan(false);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'jawaban_teks' => 'Jawaban essay baru.',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'jawaban_teks' => 'Jawaban essay baru.',
            'option_id' => null,
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'jawaban_teks' => '   ',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
        ]);
    }

    public function test_mode_serempak_menolak_soal_essay(): void
    {
        $admin = User::create([
            'nama' => 'Admin',
            'username' => 'admin-serempak-essay',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Essay',
            'created_by' => $admin->id,
        ]);

        $essay = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Jelaskan sesuatu.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Serempak Essay',
            'mode' => 'serempak',
            'status' => 'lobby',
            'display_token' => 'token-serempak-essay',
            'created_by' => $admin->id,
        ]);

        try {
            app(QuizEngine::class)->mulaiSesi($session);
            $this->fail('Mode Serempak seharusnya menolak soal essay.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sesi', $e->errors());
        }

        $this->assertSame('lobby', $session->fresh()->status);
    }

    /**
     * @return array{0: User, 1: User, 2: QuizSession, 3: Attempt, 4: Question, 5: Question, 6: Option}
     */
    private function buatAcakBerjalan(bool $denganJawaban = true): array
    {
        $admin = User::create([
            'nama' => 'Admin',
            'username' => 'admin-essay',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-admin',
        ]);

        $peserta = User::create([
            'nama' => 'Peserta',
            'username' => 'peserta-essay',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
            'current_session_token' => 'token-peserta',
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Essay',
            'created_by' => $admin->id,
        ]);

        $pg = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Ibu kota Indonesia?',
            'tipe' => 'pilihan_ganda',
            'bobot' => 2,
            'durasi_detik' => 60,
        ]);

        $benar = Option::create([
            'question_id' => $pg->id,
            'teks' => 'Jakarta',
            'is_correct' => true,
        ]);

        Option::create([
            'question_id' => $pg->id,
            'teks' => 'Bandung',
            'is_correct' => false,
        ]);

        $essay = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Jelaskan perbedaan HTTP dan HTTPS.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
            'kunci_essay' => 'HTTPS memakai TLS.',
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Essay',
            'mode' => 'acak',
            'status' => 'berjalan',
            'display_token' => 'token-acak-essay',
            'created_by' => $admin->id,
        ]);

        $attempt = Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'berjalan',
            'mulai_at' => now()->getTimestampMs(),
            'batas_akhir_at' => now()->addHour()->getTimestampMs(),
            'bergabung_at' => now(),
        ]);

        AttemptQuestion::create([
            'attempt_id' => $attempt->id,
            'question_id' => $pg->id,
            'urutan' => 1,
            'urutan_opsi_json' => [$benar->id],
        ]);

        AttemptQuestion::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'urutan' => 2,
            'urutan_opsi_json' => [],
        ]);

        if ($denganJawaban) {
            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $pg->id,
                'option_id' => $benar->id,
                'waktu_jawab_ms' => 1000,
                'skor' => 0,
                'answered_at' => now(),
            ]);

            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $essay->id,
                'jawaban_teks' => 'HTTP tidak terenkripsi, HTTPS memakai TLS.',
                'waktu_jawab_ms' => 2000,
                'skor' => 0,
                'answered_at' => now(),
            ]);
        }

        return [$admin, $peserta, $session, $attempt, $pg, $essay, $benar];
    }
}
