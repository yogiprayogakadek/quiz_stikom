<?php

namespace Tests\Unit;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\EssayGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EssayGraderAuditAndEvaluateTest extends TestCase
{
    use RefreshDatabase;

    public function test_grader_audit_and_evaluate_persists_data_and_logs(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'ai_detection' => ['score' => 82, 'reason' => 'Khas teks AI.'],
                            'suggestions' => [
                                'ketat' => ['score' => 12, 'feedback' => 'Ketat.'],
                                'seimbang' => ['score' => 16, 'confidence' => 90, 'feedback' => 'Seimbang.'],
                                'longgar' => ['score' => 19, 'feedback' => 'Longgar.'],
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $admin = User::create(['nama' => 'Admin', 'username' => 'adm_gr', 'password' => 'secret', 'role' => 'admin']);
        $quiz = Quiz::create(['judul' => 'Kuis Grader', 'created_by' => $admin->id]);
        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Grader',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'tok-gr',
            'created_by' => $admin->id,
        ]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'tipe' => 'essay',
            'teks' => 'Jelaskan TCP/IP',
            'bobot' => 20,
            'urutan' => 1,
        ]);
        $user = User::create(['nama' => 'Siswa', 'username' => 'siswa_gr', 'password' => 'secret']);
        $attempt = Attempt::create([
            'quiz_session_id' => $session->id,
            'user_id' => $user->id,
            'status' => 'selesai',
            'started_at' => now(),
        ]);
        $answer = Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answered_at' => now(),
            'jawaban_teks' => 'TCP/IP adalah protokol komunikasi jaringan.',
        ]);

        $grader = app(EssayGrader::class);
        $result = $grader->auditAndEvaluate($answer);

        $this->assertSame(82, $result['ai_detection']['score']);
        $this->assertSame(16, $result['suggestions']['seimbang']['score']);

        $answer->refresh();
        $this->assertSame(82, $answer->ai_generated_score);
        $this->assertSame('Khas teks AI.', $answer->ai_generated_reason);
        $this->assertNotNull($answer->ai_generated_at);
        $this->assertSame(16, $answer->ai_suggested_score);
        $this->assertSame(90, $answer->ai_confidence);
        $this->assertSame('Seimbang.', $answer->ai_feedback);
        $this->assertNotNull($answer->ai_reviewed_at);
        $this->assertNotNull($answer->ai_suggestions_json);
        $this->assertSame(12, $answer->ai_suggestions_json['ketat']['score']);
        $this->assertSame(16, $answer->ai_suggestions_json['seimbang']['score']);
        $this->assertSame(19, $answer->ai_suggestions_json['longgar']['score']);
    }
}
