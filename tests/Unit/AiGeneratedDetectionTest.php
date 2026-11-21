<?php

namespace Tests\Unit;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\AiQuestionService;
use App\Services\EssayGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiGeneratedDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_detect_ai_generated_returns_score_and_reason(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => '{"score":88,"reason":"Struktur kalimat sangat formal dengan transisi khas model bahasa generatif."}'],
                ]],
            ]),
        ]);

        $service = app(AiQuestionService::class);
        $result = $service->detectAiGenerated(
            'Jelaskan perbedaan TCP dan UDP!',
            'TCP adalah protokol berorientasi koneksi yang menjamin pengiriman paket, sedangkan UDP tidak.'
        );

        $this->assertSame(88, $result['score']);
        $this->assertStringContainsString('model bahasa generatif', $result['reason']);
    }

    public function test_essay_grader_detect_ai_updates_answer(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => '{"score":75,"reason":"Pola penjelasan terstruktur rapi menyerupai keluaran LLM."}'],
                ]],
            ]),
        ]);

        $admin = User::create(['nama' => 'Admin', 'username' => 'admin_ai', 'password' => 'secret', 'role' => 'admin']);
        $quiz = Quiz::create(['judul' => 'Kuis AI', 'created_by' => $admin->id]);
        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi AI',
            'mode' => 'standard',
            'status' => 'completed',
            'display_token' => 'tok-ai',
            'created_by' => $admin->id,
        ]);

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'tipe' => 'essay',
            'teks' => 'Jelaskan konsep OOP!',
            'bobot' => 10,
            'urutan' => 1,
        ]);

        $student = User::create(['nama' => 'Siswa Test', 'username' => 'siswa_test', 'password' => 'secret']);
        $attempt = Attempt::create([
            'quiz_session_id' => $session->id,
            'user_id' => $student->id,
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $answer = Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answered_at' => now(),
            'jawaban_teks' => 'OOP adalah paradigma pemrograman berbasis objek yang memiliki pilar enkapsulasi, pewarisan, dan polimorfisme.',
        ]);

        $grader = app(EssayGrader::class);
        $result = $grader->detectAi($answer);

        $this->assertSame(75, $result['score']);
        $this->assertStringContainsString('menyerupai keluaran LLM', $result['reason']);

        $answer->refresh();
        $this->assertSame(75, $answer->ai_generated_score);
        $this->assertStringContainsString('menyerupai keluaran LLM', $answer->ai_generated_reason);
        $this->assertNotNull($answer->ai_generated_at);
    }
}
