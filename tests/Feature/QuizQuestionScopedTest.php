<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QuizQuestionScopedTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_quiz_first_then_adds_question_to_that_quiz(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kuis',
            'username' => 'admin-quiz-test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-token',
        ]);

        $kelas = Kelas::create([
            'nama' => 'Kelas TI A',
            'pin' => '654321',
            'created_by' => $admin->id,
        ]);

        // 1. Create quiz requiring kelas_id
        $responseQuiz = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->post(route('admin.quizzes.store'), [
                'kelas_id' => $kelas->id,
                'judul' => 'Kuis Algoritma & Struktur Data',
                'jumlah_soal' => null,
            ]);

        $quiz = Quiz::where('judul', 'Kuis Algoritma & Struktur Data')->first();
        $this->assertNotNull($quiz);
        $this->assertEquals($kelas->id, $quiz->kelas_id);
        $responseQuiz->assertRedirect(route('admin.quizzes.questions.index', $quiz));

        // 2. Add question scoped to this quiz
        $responseQuestion = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'teks' => 'Berapa kompleksitas waktu binary search?',
                'tipe' => 'pilihan_ganda',
                'bobot' => 5,
                'durasi_detik' => 45,
                'correct' => 0,
                'opsi' => [
                    ['teks' => 'O(log n)'],
                    ['teks' => 'O(n)'],
                    ['teks' => 'O(n^2)'],
                ],
            ]);

        $responseQuestion->assertRedirect(route('admin.quizzes.questions.index', $quiz));

        $question = Question::where('teks', 'Berapa kompleksitas waktu binary search?')->first();
        $this->assertNotNull($question);
        $this->assertEquals($quiz->id, $question->quiz_id);
        $this->assertEquals(1, $quiz->questions()->count());
        $this->assertEquals(3, $question->options()->count());
        $this->assertTrue($question->correctOption()->is_correct);
        $this->assertEquals('O(log n)', $question->correctOption()->teks);
    }

    public function test_question_from_other_quiz_cannot_be_edited_or_deleted_via_mismatched_quiz_route(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kuis',
            'username' => 'admin-quiz-scope',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-scope-token',
        ]);

        $kelas1 = Kelas::create(['nama' => 'Kelas 1', 'pin' => '111111', 'created_by' => $admin->id]);
        $kelas2 = Kelas::create(['nama' => 'Kelas 2', 'pin' => '222222', 'created_by' => $admin->id]);

        $quiz1 = Quiz::create(['kelas_id' => $kelas1->id, 'judul' => 'Kuis 1', 'created_by' => $admin->id]);
        $quiz2 = Quiz::create(['kelas_id' => $kelas2->id, 'judul' => 'Kuis 2', 'created_by' => $admin->id]);

        $question1 = Question::create([
            'quiz_id' => $quiz1->id,
            'teks' => 'Soal milik kuis 1',
            'tipe' => 'pilihan_ganda',
            'bobot' => 1,
            'durasi_detik' => 30,
        ]);

        // Access question 1 under quiz 2 -> must 404
        $resEdit = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-scope-token'])
            ->get(route('admin.quizzes.questions.edit', [$quiz2, $question1]));

        $resEdit->assertNotFound();

        // Update question 1 under quiz 2 -> must 404
        $resUpdate = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-scope-token'])
            ->put(route('admin.quizzes.questions.update', [$quiz2, $question1]), [
                'teks' => 'Diubah lewat kuis 2',
                'tipe' => 'pilihan_ganda',
                'bobot' => 1,
                'durasi_detik' => 30,
                'correct' => 0,
                'opsi' => [['teks' => 'A'], ['teks' => 'B']],
            ]);

        $resUpdate->assertNotFound();
        $this->assertEquals('Soal milik kuis 1', $question1->fresh()->teks);

        // Delete question 1 under quiz 2 -> must 404
        $resDelete = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-scope-token'])
            ->delete(route('admin.quizzes.questions.destroy', [$quiz2, $question1]));

        $resDelete->assertNotFound();
        $this->assertFalse($question1->fresh()->trashed());
    }

    public function test_deleting_quiz_cascades_to_its_questions(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kuis',
            'username' => 'admin-quiz-del',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-del-token',
        ]);

        $kelas = Kelas::create(['nama' => 'Kelas Hapus', 'pin' => '333333', 'created_by' => $admin->id]);
        $quiz = Quiz::create(['kelas_id' => $kelas->id, 'judul' => 'Kuis Dihapus', 'created_by' => $admin->id]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Soal kuis akan dihapus',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-del-token'])
            ->delete(route('admin.quizzes.destroy', $quiz));

        $this->assertDatabaseMissing('quizzes', ['id' => $quiz->id]);
        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
    }

    public function test_participant_must_enroll_class_to_see_quizzes(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kuis',
            'username' => 'admin-quiz-enroll',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-enroll-token',
        ]);

        $peserta = User::create([
            'nama' => 'Peserta Uji',
            'username' => 'peserta-enroll',
            'password' => Hash::make('password'),
            'role' => 'peserta',
            'current_session_token' => 'peserta-enroll-token',
        ]);

        $kelas = Kelas::create([
            'nama' => 'Pemrograman Web',
            'pin' => '998877',
            'created_by' => $admin->id,
        ]);

        $quiz = Quiz::create([
            'kelas_id' => $kelas->id,
            'judul' => 'Ujian Tengah Semester',
            'created_by' => $admin->id,
        ]);

        // Sebelum enroll: halaman kelas hanya menampilkan form PIN, kuis tidak muncul
        $resBelum = $this->actingAs($peserta)
            ->withSession(['auth_token' => 'peserta-enroll-token'])
            ->get(route('peserta.kelas.show', $kelas));

        $resBelum->assertOk();
        $resBelum->assertSee('PIN kelas');
        $resBelum->assertDontSee('Ujian Tengah Semester');

        // Peserta enroll dengan PIN
        $resMasuk = $this->actingAs($peserta)
            ->withSession(['auth_token' => 'peserta-enroll-token'])
            ->post(route('peserta.kelas.masuk', $kelas), ['pin' => '998877']);

        $resMasuk->assertRedirect(route('peserta.kelas.show', $kelas));

        // Setelah enroll: kuis kelas terlihat
        $resSudah = $this->actingAs($peserta)
            ->withSession(['auth_token' => 'peserta-enroll-token'])
            ->get(route('peserta.kelas.show', $kelas));

        $resSudah->assertOk();
        $resSudah->assertSee('Ujian Tengah Semester');
    }
}
