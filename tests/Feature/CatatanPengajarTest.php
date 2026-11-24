<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\AuditLog;
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

    public function test_admin_menyimpan_nilai_dan_catatan_sekaligus(): void
    {
        [$admin, $attempt, $answer] = $this->jawabanEssay();

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$answer->question_id => 7],
                'catatan' => [$answer->question_id => 'Kesimpulan kurang tajam.'],
            ])
            ->assertRedirect();

        $answer->refresh();
        $this->assertSame(7, $answer->skor);
        $this->assertSame('Kesimpulan kurang tajam.', $answer->catatan_pengajar);
    }

    public function test_auto_grade_tidak_menghapus_catatan_guru(): void
    {
        [, , $answer] = $this->jawabanEssay();
        $answer->update(['catatan_pengajar' => 'Catatan guru.']);

        app(\App\Services\EssayGrader::class)->applyFinal($answer, 5, null, true);

        $this->assertSame('Catatan guru.', $answer->fresh()->catatan_pengajar);
    }

    public function test_catatan_lebih_dari_2000_karakter_ditolak(): void
    {
        [$admin, $attempt, $answer] = $this->jawabanEssay();

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$answer->question_id => 5],
                'catatan' => [$answer->question_id => str_repeat('a', 2001)],
            ])
            ->assertSessionHasErrors('catatan.'.$answer->question_id);
    }

    public function test_ruang_penilaian_menampilkan_isian_catatan(): void
    {
        [$admin, $attempt, $answer] = $this->jawabanEssay();

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get(route('admin.reports.attempt', ['attempt' => $attempt, 'soal' => $answer->question_id]))
            ->assertOk()
            ->assertSee('name="catatan['.$answer->question_id.']"', false)
            ->assertSee('Catatan untuk peserta');
    }

    public function test_catatan_tersembunyi_selama_masih_ada_essay_belum_dinilai(): void
    {
        [$admin, $attempt, $answer] = $this->jawabanEssay();
        $this->tambahEssayKedua($attempt);
        app(\App\Services\EssayGrader::class)->applyFinal($answer, 8, $admin->id, catatan: 'Baik.');

        $this->actingAs($attempt->user)
            ->withSession(['auth_token' => $attempt->user->current_session_token])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertDontSee('Catatan pengajar');
    }

    public function test_catatan_tampil_setelah_semua_essay_dinilai_walau_pembahasan_tertutup(): void
    {
        [$admin, $attempt, $answer] = $this->jawabanEssay(['pembahasan_mode' => 'tersembunyi']);
        app(\App\Services\EssayGrader::class)->applyFinal($answer, 8, $admin->id, catatan: 'Argumen kuat, contoh kurang.');

        $this->actingAs($attempt->user)
            ->withSession(['auth_token' => $attempt->user->current_session_token])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertSee('Catatan pengajar')
            ->assertSee('Argumen kuat, contoh kurang.');
    }

    public function test_essay_tidak_dijawab_tidak_menghalangi_catatan_tampil(): void
    {
        [$admin, $attempt, $answer] = $this->jawabanEssay();
        $this->tambahEssayKedua($attempt, dijawab: false);
        app(\App\Services\EssayGrader::class)->applyFinal($answer, 8, $admin->id, catatan: 'Baik.');

        $this->actingAs($attempt->user)
            ->withSession(['auth_token' => $attempt->user->current_session_token])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertSee('Catatan pengajar')
            ->assertSee('Baik.');
    }

    public function test_catatan_pada_jawaban_terdinilai_tidak_mengubah_nilai_dan_log(): void
    {
        [$admin, , $answer] = $this->jawabanEssay();
        $grader = app(\App\Services\EssayGrader::class);

        $grader->applyFinal($answer, 6, $admin->id, catatan: 'Catatan pertama.');
        $answer->refresh();
        $dinilaiPertama = $answer->dinilai_at?->toDateTimeString();

        $this->travel(5)->seconds();

        $grader->applyFinal($answer, 6, $admin->id, catatan: 'Catatan diperbarui.');
        $answer->refresh();

        $this->assertSame(6, $answer->skor);
        $this->assertSame($dinilaiPertama, $answer->dinilai_at?->toDateTimeString());
        $this->assertSame('Catatan diperbarui.', $answer->catatan_pengajar);
        $this->assertSame(1, AuditLog::where('action', 'essay_score_changed')->where('subject_id', $answer->id)->count());
    }

    public function test_essay_tanpa_jawaban_tidak_bisa_dinilai_atau_diberi_catatan(): void
    {
        [$admin, $attempt, $answer] = $this->jawabanEssay();
        $answer->update(['jawaban_teks' => null]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$answer->question_id => 5],
                'catatan' => [$answer->question_id => 'Tidak seharusnya tersimpan.'],
            ])
            ->assertSessionHasErrors('nilai.'.$answer->question_id);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$answer->question_id => 0],
                'catatan' => [$answer->question_id => 'Tidak seharusnya tersimpan.'],
            ])
            ->assertRedirect();

        $answer->refresh();
        $this->assertNull($answer->catatan_pengajar);
        $this->assertNull($answer->dinilai_at);
    }

    private function tambahEssayKedua(Attempt $attempt, bool $dijawab = true): void
    {
        $essay = Question::create([
            'quiz_id' => $attempt->quizSession->quiz_id,
            'teks' => 'Soal essay kedua catatan.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);

        AttemptQuestion::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'urutan' => 2,
            'urutan_opsi_json' => [],
        ]);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'jawaban_teks' => $dijawab ? 'Jawaban kedua yang belum dinilai.' : null,
            'waktu_jawab_ms' => 1000,
            'skor' => 0,
            'answered_at' => now(),
        ]);
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
            'nilai_ditampilkan' => true,
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
