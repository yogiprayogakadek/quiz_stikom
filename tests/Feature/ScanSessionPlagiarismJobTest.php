<?php

namespace Tests\Feature;

use App\Jobs\ScanSessionPlagiarismJob;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\TextSimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanSessionPlagiarismJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_scans_and_updates_essay_similarity_in_session(): void
    {
        $admin = User::create(['nama' => 'Admin', 'username' => 'admin_scan', 'password' => 'secret', 'role' => 'admin']);
        $quiz = Quiz::create(['judul' => 'Kuis Plagiarisme', 'created_by' => $admin->id]);
        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Plagiarisme',
            'mode' => 'standard',
            'status' => 'completed',
            'display_token' => 'tok-scan',
            'created_by' => $admin->id,
        ]);

        $qEssay = Question::create([
            'quiz_id' => $quiz->id,
            'tipe' => 'essay',
            'teks' => 'Jelaskan konsep arsitektur MVC!',
            'bobot' => 20,
            'urutan' => 1,
        ]);

        $u1 = User::create(['nama' => 'Budi Santoso', 'username' => 'budi', 'password' => 'secret']);
        $u2 = User::create(['nama' => 'Siti Rahma', 'username' => 'siti', 'password' => 'secret']);
        $u3 = User::create(['nama' => 'Andi Wijaya', 'username' => 'andi', 'password' => 'secret']);

        $att1 = Attempt::create(['quiz_session_id' => $session->id, 'user_id' => $u1->id, 'status' => 'completed', 'started_at' => now()]);
        $att2 = Attempt::create(['quiz_session_id' => $session->id, 'user_id' => $u2->id, 'status' => 'completed', 'started_at' => now()]);
        $att3 = Attempt::create(['quiz_session_id' => $session->id, 'user_id' => $u3->id, 'status' => 'completed', 'started_at' => now()]);

        $teksSama = 'MVC adalah pola arsitektur perangkat lunak yang memisahkan aplikasi menjadi Model, View, dan Controller.';

        $ans1 = Answer::create([
            'attempt_id' => $att1->id,
            'question_id' => $qEssay->id,
            'answered_at' => now(),
            'jawaban_teks' => $teksSama,
        ]);

        $ans2 = Answer::create([
            'attempt_id' => $att2->id,
            'question_id' => $qEssay->id,
            'answered_at' => now(),
            'jawaban_teks' => $teksSama,
        ]);

        $ans3 = Answer::create([
            'attempt_id' => $att3->id,
            'question_id' => $qEssay->id,
            'answered_at' => now(),
            'jawaban_teks' => 'Model View Controller memisahkan data antarmuka pengguna dan logika kontrol.',
        ]);

        $job = new ScanSessionPlagiarismJob($session->id);
        $job->handle(new TextSimilarityService());

        $ans1->refresh();
        $ans2->refresh();
        $ans3->refresh();

        $this->assertSame(100, $ans1->similarity_score);
        $this->assertNotEmpty($ans1->similarity_data);
        $this->assertSame('Siti Rahma', $ans1->similarity_data[0]['student_name']);
        $this->assertNotNull($ans1->similarity_checked_at);

        $this->assertSame(100, $ans2->similarity_score);
        $this->assertSame('Budi Santoso', $ans2->similarity_data[0]['student_name']);

        $this->assertLessThan(100, $ans3->similarity_score);
    }

    public function test_it_handles_single_answer_or_same_user_gracefully(): void
    {
        $admin = User::create(['nama' => 'Admin2', 'username' => 'admin_scan2', 'password' => 'secret', 'role' => 'admin']);
        $quiz = Quiz::create(['judul' => 'Kuis Single', 'created_by' => $admin->id]);
        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Single',
            'mode' => 'standard',
            'status' => 'completed',
            'display_token' => 'tok-scan2',
            'created_by' => $admin->id,
        ]);

        $qEssay = Question::create([
            'quiz_id' => $quiz->id,
            'tipe' => 'essay',
            'teks' => 'Jelaskan arti AI!',
            'bobot' => 10,
            'urutan' => 1,
        ]);

        $u1 = User::create(['nama' => 'User Tunggal', 'username' => 'tunggal', 'password' => 'secret']);
        $att1 = Attempt::create(['quiz_session_id' => $session->id, 'user_id' => $u1->id, 'status' => 'completed', 'started_at' => now()]);

        $ans1 = Answer::create([
            'attempt_id' => $att1->id,
            'question_id' => $qEssay->id,
            'answered_at' => now(),
            'jawaban_teks' => 'AI adalah kecerdasan buatan.',
        ]);

        $job = new ScanSessionPlagiarismJob($session->id);
        $job->handle(new TextSimilarityService());

        $ans1->refresh();

        $this->assertNull($ans1->similarity_score);
        $this->assertSame([], $ans1->similarity_data);
        $this->assertNotNull($ans1->similarity_checked_at);
    }
}
