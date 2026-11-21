<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAuditAndEvaluateEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_call_audit_and_evaluate_endpoint(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'ai_detection' => ['score' => 90, 'reason' => 'Pola LLM.'],
                            'suggestions' => [
                                'ketat' => ['score' => 15, 'feedback' => 'Ketat.'],
                                'seimbang' => ['score' => 18, 'confidence' => 95, 'feedback' => 'Seimbang.'],
                                'longgar' => ['score' => 20, 'feedback' => 'Longgar.'],
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $org = Organization::create(['name' => 'Org', 'slug' => 'org-ae', 'type' => 'education', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $admin = User::create(['nama' => 'Admin', 'username' => 'admin_ae', 'password' => Hash::make('secret'), 'role' => 'admin', 'current_session_token' => 'tok-adm']);
        $org->users()->attach($admin->id, ['role' => 'owner', 'status' => 'active']);

        $quiz = Quiz::create(['organization_id' => $org->id, 'judul' => 'Kuis Endpoint', 'created_by' => $admin->id]);
        $session = QuizSession::create(['organization_id' => $org->id, 'quiz_id' => $quiz->id, 'nama' => 'Sesi', 'mode' => 'acak', 'status' => 'selesai', 'display_token' => 'tok-s', 'created_by' => $admin->id]);
        $question = Question::create(['organization_id' => $org->id, 'quiz_id' => $quiz->id, 'tipe' => 'essay', 'teks' => 'Soal 1', 'bobot' => 20, 'urutan' => 1]);
        $student = User::create(['nama' => 'Student', 'username' => 'stu_ae', 'password' => Hash::make('secret'), 'role' => 'peserta']);
        $attempt = Attempt::create(['organization_id' => $org->id, 'quiz_session_id' => $session->id, 'user_id' => $student->id, 'status' => 'selesai', 'started_at' => now()]);
        $answer = Answer::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'answered_at' => now(), 'jawaban_teks' => 'Jawaban peserta']);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'current_organization_id' => $org->id])
            ->postJson(route('admin.answers.audit-and-evaluate', $answer->id));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ai_detection.score', 90)
            ->assertJsonPath('suggestions.seimbang.score', 18);
    }
}
