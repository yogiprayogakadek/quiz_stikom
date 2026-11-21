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

class AdminPlagiarismEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_trigger_session_plagiarism_scan(): void
    {
        [$org, $admin, $session] = $this->setupQuizSession();

        $response = $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'current_organization_id' => $org->id,
            ])
            ->post(route('admin.reports.session.scan-plagiarism', $session->id));

        $response->assertRedirect();
        $response->assertSessionHas('sukses');
    }

    public function test_admin_can_trigger_ai_detection_for_single_answer(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => '{"score":92,"reason":"Pola kalimat sangat mirip pola output generator LLM."}'],
                ]],
            ]),
        ]);

        [$org, $admin, $session, $answer] = $this->setupQuizAnswer();

        $response = $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'current_organization_id' => $org->id,
            ])
            ->postJson(route('admin.answers.detect-ai', $answer->id));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('score', 92);

        $answer->refresh();
        $this->assertSame(92, $answer->ai_generated_score);
    }

    private function setupQuizSession(): array
    {
        $org = Organization::create([
            'name' => 'Org Scan',
            'slug' => 'org-scan',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin Org',
            'username' => 'admin_org_scan',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'current_session_token' => 'admin-org-scan-token',
        ]);

        $org->users()->attach($admin->id, ['role' => 'owner', 'status' => 'active']);

        $quiz = Quiz::create([
            'organization_id' => $org->id,
            'judul' => 'Kuis Endpoint Scan',
            'created_by' => $admin->id,
        ]);

        $session = QuizSession::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Endpoint',
            'mode' => 'standard',
            'status' => 'completed',
            'display_token' => 'tok-ep',
            'created_by' => $admin->id,
        ]);

        return [$org, $admin, $session];
    }

    private function setupQuizAnswer(): array
    {
        [$org, $admin, $session] = $this->setupQuizSession();

        $question = Question::create([
            'organization_id' => $org->id,
            'quiz_id' => $session->quiz_id,
            'tipe' => 'essay',
            'teks' => 'Jelaskan konsep Database Indexing!',
            'bobot' => 10,
            'urutan' => 1,
        ]);

        $student = User::create([
            'nama' => 'Student Scan',
            'username' => 'student_scan',
            'password' => Hash::make('secret'),
            'role' => 'peserta',
        ]);
        $org->users()->attach($student->id, ['role' => 'participant', 'status' => 'active']);

        $attempt = Attempt::create([
            'organization_id' => $org->id,
            'quiz_session_id' => $session->id,
            'user_id' => $student->id,
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $answer = Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answered_at' => now(),
            'jawaban_teks' => 'Database indexing mempercepat pencarian data dengan struktur B-Tree.',
        ]);

        return [$org, $admin, $session, $answer];
    }
}
