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

class PlagiarismUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_attempt_report_displays_plagiarism_and_ai_badges(): void
    {
        [$org, $admin, $attempt, $answer] = $this->setupAttemptWithPlagiarism();

        $response = $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'current_organization_id' => $org->id,
            ])
            ->get(route('admin.reports.attempt', $attempt->id));

        $response->assertOk()
            ->assertSee('85% mirip')
            ->assertSee('Audit AI');
    }

    public function test_session_report_displays_plagiarism_rescan_button_and_summary(): void
    {
        [$org, $admin, $session] = $this->setupSessionWithEssay();

        $response = $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'current_organization_id' => $org->id,
            ])
            ->get(route('admin.reports.session', $session->id));

        $response->assertOk()
            ->assertSee('Pindai Ulang Plagiarisme');
    }

    private function setupAttemptWithPlagiarism(): array
    {
        $org = Organization::create([
            'name' => 'Org UI',
            'slug' => 'org-ui',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin UI',
            'username' => 'admin_ui',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'current_session_token' => 'admin-ui-token',
        ]);
        $org->users()->attach($admin->id, ['role' => 'owner', 'status' => 'active']);

        $quiz = Quiz::create([
            'organization_id' => $org->id,
            'judul' => 'Kuis UI Plagiarisme',
            'created_by' => $admin->id,
        ]);

        $session = QuizSession::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi UI',
            'mode' => 'acak',
            'status' => 'completed',
            'display_token' => 'tok-ui',
            'created_by' => $admin->id,
        ]);

        $question = Question::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'tipe' => 'essay',
            'teks' => 'Jelaskan konsep Polymorphism dalam OOP!',
            'bobot' => 20,
            'urutan' => 1,
        ]);

        $student = User::create([
            'nama' => 'Peserta UI',
            'username' => 'peserta_ui',
            'password' => Hash::make('secret'),
            'role' => 'peserta',
        ]);
        $org->users()->attach($student->id, ['role' => 'participant', 'status' => 'active']);

        $attempt = Attempt::create([
            'organization_id' => $org->id,
            'quiz_session_id' => $session->id,
            'user_id' => $student->id,
            'status' => 'selesai',
            'started_at' => now(),
        ]);

        AttemptQuestion::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'urutan' => 1,
            'urutan_opsi_json' => [],
        ]);

        $answer = Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answered_at' => now(),
            'jawaban_teks' => 'Polymorphism adalah kemampuan objek memiliki banyak bentuk.',
            'similarity_score' => 85,
            'similarity_data' => [
                [
                    'answer_id' => 999,
                    'student_name' => 'Budi Santoso',
                    'score' => 85,
                    'text' => 'Polymorphism memungkinkan suatu objek memiliki berbagai bentuk metode.',
                ],
            ],
            'similarity_checked_at' => now(),
        ]);

        return [$org, $admin, $attempt, $answer];
    }

    private function setupSessionWithEssay(): array
    {
        $org = Organization::create([
            'name' => 'Org Sess',
            'slug' => 'org-sess',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin Sess',
            'username' => 'admin_sess',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'current_session_token' => 'admin-sess-token',
        ]);
        $org->users()->attach($admin->id, ['role' => 'owner', 'status' => 'active']);

        $quiz = Quiz::create([
            'organization_id' => $org->id,
            'judul' => 'Kuis Sesi Essay',
            'created_by' => $admin->id,
        ]);

        $session = QuizSession::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Dengan Essay',
            'mode' => 'standard',
            'status' => 'completed',
            'display_token' => 'tok-sess',
            'created_by' => $admin->id,
        ]);

        Question::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'tipe' => 'essay',
            'teks' => 'Soal Essay Sesi',
            'bobot' => 10,
            'urutan' => 1,
        ]);

        return [$org, $admin, $session];
    }
}
