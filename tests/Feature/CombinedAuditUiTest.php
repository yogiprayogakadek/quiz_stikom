<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CombinedAuditUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_attempt_report_renders_combined_audit_button_and_mode_pills(): void
    {
        $org = Organization::create(['name' => 'Org UI', 'slug' => 'org-ui-test', 'type' => 'education', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $admin = User::create(['nama' => 'Admin UI', 'username' => 'adm_ui_test', 'password' => Hash::make('secret'), 'role' => 'admin', 'current_session_token' => 'tok-ui']);
        $org->users()->attach($admin->id, ['role' => 'owner', 'status' => 'active']);

        $quiz = Quiz::create(['organization_id' => $org->id, 'judul' => 'Kuis UI', 'created_by' => $admin->id]);
        $session = QuizSession::create(['organization_id' => $org->id, 'quiz_id' => $quiz->id, 'nama' => 'Sesi UI', 'mode' => 'acak', 'status' => 'selesai', 'display_token' => 'tok-sui', 'created_by' => $admin->id]);
        $question = Question::create(['organization_id' => $org->id, 'quiz_id' => $quiz->id, 'tipe' => 'essay', 'teks' => 'Soal UI', 'bobot' => 20, 'urutan' => 1]);
        $student = User::create(['nama' => 'Student UI', 'username' => 'stu_ui_test', 'password' => Hash::make('secret'), 'role' => 'peserta']);
        $attempt = Attempt::create(['organization_id' => $org->id, 'quiz_session_id' => $session->id, 'user_id' => $student->id, 'status' => 'selesai', 'started_at' => now()]);
        AttemptQuestion::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'urutan' => 1, 'urutan_opsi_json' => []]);

        $answer = Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answered_at' => now(),
            'jawaban_teks' => 'Jawaban UI',
            'ai_generated_score' => 88,
            'ai_suggestions_json' => [
                'ketat' => ['score' => 12, 'feedback' => 'Saran ketat.'],
                'seimbang' => ['score' => 16, 'feedback' => 'Saran seimbang.'],
                'longgar' => ['score' => 20, 'feedback' => 'Saran longgar.'],
            ],
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'current_organization_id' => $org->id])
            ->get(route('admin.reports.attempt', $attempt->id));

        $response->assertOk()
            ->assertSee('Audit &amp; Saran Semua Essay', false)
            ->assertSee('Ketat: 12')
            ->assertSee('Seimbang: 16')
            ->assertSee('Longgar: 20')
            ->assertSee('Terminal Log')
            ->assertSee('x-ref="terminalBox"', false);
    }
}
