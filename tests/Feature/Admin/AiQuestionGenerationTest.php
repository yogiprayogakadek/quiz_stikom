<?php

namespace Tests\Feature\Admin;

use App\Jobs\GenerateAiQuestionsJob;
use App\Models\AiGeneration;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\AiQuestionService;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AiQuestionGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama' => 'Admin Kuis',
            'username' => 'admin-ai-test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-token',
        ]);

        $this->quiz = Quiz::create([
            'judul' => 'Pemrograman Web',
            'deskripsi' => 'Kuis pemrograman web dasar',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_view_ai_form(): void
    {
        $mockService = Mockery::mock(AiQuestionService::class);
        $mockService->shouldReceive('getAvailableModels')
            ->once()
            ->andReturn([
                'Google' => ['ag/gemini-3.8-flash-medium'],
            ]);
        $this->app->instance(AiQuestionService::class, $mockService);

        $response = $this->actingAs($this->admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->get(route('admin.quizzes.questions.ai.form', $this->quiz));

        $response->assertOk();
        $response->assertViewIs('admin.questions.ai-form');
        $response->assertViewHas('quiz', $this->quiz);
        $response->assertViewHas('models');
        $response->assertSee('Generate Soal AI');
        $response->assertSee('ag/gemini-3.8-flash-medium');
    }

    public function test_cannot_access_ai_form_if_quiz_is_locked(): void
    {
        QuizSession::create([
            'quiz_id' => $this->quiz->id,
            'nama' => 'Sesi Ujian',
            'mode' => 'mandiri',
            'status' => 'berjalan',
            'display_token' => 'display-token-ai-test',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->get(route('admin.quizzes.questions.ai.form', $this->quiz));

        $response->assertRedirect(route('admin.quizzes.questions.index', $this->quiz));
        $response->assertSessionHasErrors(['soal']);
    }

    public function test_ai_generate_mengantrekan_job_dan_pratinjau_menampilkan_hasil(): void
    {
        Queue::fake();

        $payload = $this->aiPayload();

        $response = $this->actingAs($this->admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->post(route('admin.quizzes.questions.ai.generate', $this->quiz), $payload);

        $generation = AiGeneration::first();
        $this->assertNotNull($generation);
        $this->assertSame('pending', $generation->status);

        $response->assertRedirect(route('admin.quizzes.questions.ai.progress', [$this->quiz, $generation]));
        Queue::assertPushed(GenerateAiQuestionsJob::class, fn ($job) => $job->generationId === $generation->id);

        $mockService = Mockery::mock(AiQuestionService::class);
        $mockService->shouldReceive('generateQuestions')
            ->once()
            ->andReturn([
                [
                    'teks' => 'Apa itu HTTP?',
                    'tipe' => 'pilihan_ganda',
                    'bobot' => 1,
                    'durasi_detik' => 60,
                    'pembahasan' => 'Protokol transfer hypertext',
                    'opsi' => [
                        ['teks' => 'Hypertext Transfer Protocol', 'is_correct' => true],
                        ['teks' => 'High Text Protocol', 'is_correct' => false],
                    ],
                ],
            ]);

        $this->app->instance(AiQuestionService::class, $mockService);
        (new GenerateAiQuestionsJob($generation->id))->handle(app(OrganizationContext::class));

        $generation->refresh();
        $this->assertSame('selesai', $generation->status);
        $this->assertCount(1, $generation->questions);

        $preview = $this->actingAs($this->admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->get(route('admin.quizzes.questions.ai.preview', [$this->quiz, $generation]));

        $preview->assertOk();
        $preview->assertViewIs('admin.questions.ai-preview');
        $preview->assertViewHas('questions');
        $preview->assertViewHas('params');
        $this->assertEquals(100, $preview->viewData('params')['nilai_maksimal']);
        $preview->assertSee('Apa itu HTTP?');
        $preview->assertSee('Hypertext Transfer Protocol');
        $preview->assertSee('Simpan Soal ke Kuis');
    }

    public function test_generate_soal_ditolak_saat_kuota_ai_habis(): void
    {
        config(['platform.ai_monthly_requests' => 0]);
        $organization = Organization::firstOrFail();
        $this->quiz->update(['organization_id' => $organization->id]);

        $response = $this->actingAs($this->admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->post(route('admin.quizzes.questions.ai.generate', $this->quiz), $this->aiPayload());

        $response->assertSessionHasErrors('ai');
        $this->assertDatabaseCount('ai_generations', 0);
    }

    public function test_generate_soal_mencatat_pemakaian_ai(): void
    {
        Queue::fake();
        config(['platform.ai_monthly_requests' => 10]);
        $organization = Organization::firstOrFail();
        $this->quiz->update(['organization_id' => $organization->id]);

        $this->actingAs($this->admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->post(route('admin.quizzes.questions.ai.generate', $this->quiz), array_merge($this->aiPayload(), [
                'jumlah_pg' => 6,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('ai_usage_events', [
            'organization_id' => $organization->id,
            'kind' => 'questions',
            'requests' => 2,
        ]);
    }

    public function test_halaman_progres_ai_menampilkan_status_antrean(): void
    {
        $generation = AiGeneration::create([
            'user_id' => $this->admin->id,
            'quiz_id' => $this->quiz->id,
            'status' => 'pending',
            'params' => ['jumlah_pg' => 1, 'jumlah_essay' => 0, 'nilai_maksimal' => 100],
            'total' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->get(route('admin.quizzes.questions.ai.progress', [$this->quiz, $generation]));

        $response->assertOk();
        $response->assertViewIs('admin.questions.ai-progress');
        $response->assertSee('AI sedang menyusun soal');

        $this->actingAs($this->admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->getJson(route('admin.quizzes.questions.ai.state', [$this->quiz, $generation]))
            ->assertOk()
            ->assertJsonPath('status', 'pending');
    }

    public function test_ai_store_saves_selected_questions_and_options(): void
    {
        $payload = [
            'questions' => [
                [
                    'selected' => '1',
                    'teks' => 'Apa itu MVC?',
                    'tipe' => 'pilihan_ganda',
                    'bobot' => 2,
                    'durasi_detik' => 60,
                    'pembahasan' => 'Model View Controller',
                    'correct' => 1,
                    'opsi' => [
                        ['teks' => 'Multi View Control'],
                        ['teks' => 'Model View Controller'],
                    ],
                ],
                [
                    'selected' => '1',
                    'teks' => 'Jelaskan fungsi Controller!',
                    'tipe' => 'essay',
                    'bobot' => 5,
                    'durasi_detik' => 120,
                    'pembahasan' => 'Menghubungkan model dan view',
                    'kunci_essay' => 'Fungsi logika aplikasi',
                ],
                [
                    'selected' => '0',
                    'teks' => 'Soal yang di-uncheck',
                    'tipe' => 'essay',
                    'bobot' => 1,
                    'durasi_detik' => 60,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->post(route('admin.quizzes.questions.ai.store', $this->quiz), $payload);

        $response->assertRedirect(route('admin.quizzes.questions.index', $this->quiz));

        $this->assertDatabaseHas('questions', [
            'quiz_id' => $this->quiz->id,
            'teks' => 'Apa itu MVC?',
            'tipe' => 'pilihan_ganda',
            'bobot' => 2,
        ]);

        $this->assertDatabaseHas('questions', [
            'quiz_id' => $this->quiz->id,
            'teks' => 'Jelaskan fungsi Controller!',
            'tipe' => 'essay',
        ]);

        $this->assertDatabaseMissing('questions', [
            'teks' => 'Soal yang di-uncheck',
        ]);

        $savedQuestion = Question::where('teks', 'Apa itu MVC?')->first();
        $this->assertNotNull($savedQuestion);
        $this->assertCount(2, $savedQuestion->options);
        $this->assertEquals('Model View Controller', $savedQuestion->correctOption()?->teks);
    }

    private function aiPayload(): array
    {
        return [
            'model' => 'ag/gemini-3.8-flash-medium',
            'topik' => 'Protokol Web',
            'rps_text' => 'Dasar-dasar jaringan komputer dan web',
            'jumlah_pg' => 1,
            'jumlah_essay' => 0,
            'level' => ['C2 - Memahami', 'C3 - Menerapkan'],
            'nilai_maksimal' => 100,
            'porsi_pg' => 60,
        ];
    }
}
