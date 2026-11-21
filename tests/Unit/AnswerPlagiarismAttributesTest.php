<?php

namespace Tests\Unit;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnswerPlagiarismAttributesTest extends TestCase
{
    use RefreshDatabase;

    public function test_answer_casts_plagiarism_attributes_properly(): void
    {
        $admin = User::create([
            'nama' => 'Admin',
            'username' => 'admin_test',
            'password' => 'secret',
            'role' => 'admin',
        ]);
        $quiz = Quiz::create(['judul' => 'Kuis', 'created_by' => $admin->id]);
        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Ujian',
            'mode' => 'standard',
            'status' => 'active',
            'display_token' => 'token-test',
            'created_by' => $admin->id,
        ]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'tipe' => 'essay',
            'teks' => 'Pertanyaan essay?',
            'bobot' => 10,
            'urutan' => 1,
        ]);
        $attempt = Attempt::create([
            'quiz_session_id' => $session->id,
            'user_id' => $admin->id,
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $answer = Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answered_at' => now(),
            'jawaban_teks' => 'Jawaban pengujian',
            'similarity_score' => 85,
            'similarity_data' => [['answer_id' => 2, 'student_name' => 'Budi', 'score' => 85]],
            'similarity_checked_at' => now(),
            'ai_generated_score' => 90,
            'ai_generated_reason' => 'Struktur kalimat khas LLM',
            'ai_generated_at' => now(),
        ]);

        $fresh = Answer::find($answer->id);
        $this->assertSame(85, $fresh->similarity_score);
        $this->assertIsArray($fresh->similarity_data);
        $this->assertSame('Budi', $fresh->similarity_data[0]['student_name']);
        $this->assertSame(90, $fresh->ai_generated_score);
        $this->assertSame('Struktur kalimat khas LLM', $fresh->ai_generated_reason);
        $this->assertNotNull($fresh->similarity_checked_at);
        $this->assertNotNull($fresh->ai_generated_at);
    }
}
