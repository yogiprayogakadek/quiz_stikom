<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Support\ExamTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_kuis_menyimpan_default_dan_sesi_baru_menerima_snapshot(): void
    {
        [$organization, $admin, $kelas] = $this->context('snapshot');

        $this->asAdmin($admin, $organization)
            ->post(route('admin.quizzes.store'), [
                'kelas_id' => $kelas->id,
                'judul' => 'Kuis dengan kalkulator',
                'exam_tools' => [ExamTools::CALCULATOR],
            ])
            ->assertRedirect();

        $quiz = Quiz::where('judul', 'Kuis dengan kalkulator')->firstOrFail();
        $this->assertSame([ExamTools::CALCULATOR], $quiz->examTools());
        $this->addQuestion($quiz, $organization);

        $this->asAdmin($admin, $organization)
            ->post(route('admin.sessions.store'), [
                'quiz_id' => $quiz->id,
                'nama' => 'Sesi snapshot',
                'mode' => 'acak',
                'jeda_detik' => 5,
            ])
            ->assertRedirect();

        $session = QuizSession::where('nama', 'Sesi snapshot')->firstOrFail();
        $this->assertSame([ExamTools::CALCULATOR], $session->examTools());

        $quiz->update(['exam_tools_json' => []]);
        $this->assertSame([ExamTools::CALCULATOR], $session->fresh()->examTools());
    }

    public function test_admin_dapat_override_alat_hanya_saat_sesi_draft(): void
    {
        [$organization, $admin, , $quiz, $session] = $this->scenario('override', true);

        $this->asAdmin($admin, $organization)
            ->put(route('admin.sessions.update', $session), $this->sessionPayload($session, [ExamTools::SUBNET_CALCULATOR]))
            ->assertRedirect();

        $this->assertSame([ExamTools::SUBNET_CALCULATOR], $session->fresh()->examTools());

        $session->update(['status' => 'lobby']);
        $this->asAdmin($admin, $organization)
            ->put(route('admin.sessions.update', $session), $this->sessionPayload($session, [ExamTools::CALCULATOR]))
            ->assertSessionHasErrors('mode');

        $this->assertSame([ExamTools::SUBNET_CALCULATOR], $session->fresh()->examTools());
        $this->assertTrue($quiz->is_percobaan);
    }

    public function test_server_menolak_kalkulator_subnet_pada_ujian_nyata(): void
    {
        [$organization, $admin, $kelas, $quiz, $session] = $this->scenario('real', false);

        $this->asAdmin($admin, $organization)
            ->post(route('admin.quizzes.store'), [
                'kelas_id' => $kelas->id,
                'judul' => 'Request kuis dimanipulasi',
                'exam_tools' => [ExamTools::SUBNET_CALCULATOR],
            ])
            ->assertSessionHasErrors('exam_tools');

        $this->asAdmin($admin, $organization)
            ->put(route('admin.sessions.update', $session), $this->sessionPayload($session, [ExamTools::SUBNET_CALCULATOR]))
            ->assertSessionHasErrors('exam_tools');

        $this->assertSame([], $quiz->examTools());
        $this->assertSame([], $session->fresh()->examTools());
    }

    public function test_simulasi_menerima_subnet_dan_dashboard_mengirim_ringkasan_alat(): void
    {
        [$organization, $admin, , , $session, $participant] = $this->scenario('rules', true);
        $session->update([
            'status' => 'lobby',
            'exam_tools_json' => [ExamTools::CALCULATOR, ExamTools::SUBNET_CALCULATOR],
        ]);

        $response = $this->actingAs($participant)
            ->withSession($this->authSession($organization, $participant))
            ->getJson(route('peserta.dashboard.state'));

        $response->assertOk()
            ->assertJsonPath('sesi_tersedia.0.rules.exam_tools.0.key', ExamTools::CALCULATOR)
            ->assertJsonPath('sesi_tersedia.0.rules.exam_tools.1.key', ExamTools::SUBNET_CALCULATOR);

        $html = Blade::render('<x-exam-tools :tools="$tools" />', [
            'tools' => $session->examTools(),
        ]);
        $this->assertStringContainsString('Alat bantu', $html);
        $this->assertStringContainsString('Kalkulator standar', $html);
        $this->assertStringContainsString('Kalkulator subnet IPv4', $html);
        $this->assertStringContainsString('examToolsPanel', $html);
    }

    /** @return array{Organization, User, Kelas, Quiz, QuizSession, User} */
    private function scenario(string $slug, bool $trial): array
    {
        [$organization, $admin, $kelas, $participant] = $this->context($slug);
        $quiz = Quiz::create([
            'organization_id' => $organization->id,
            'kelas_id' => $kelas->id,
            'judul' => 'Kuis '.$slug,
            'is_percobaan' => $trial,
            'exam_tools_json' => [],
            'created_by' => $admin->id,
        ]);
        $this->addQuestion($quiz, $organization);

        $session = QuizSession::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi '.$slug,
            'mode' => 'acak',
            'status' => 'draft',
            'jeda_detik' => 5,
            'timing_mode' => 'duration',
            'clipboard_policy' => 'monitor',
            'exam_tools_json' => [],
            'display_token' => 'tools-'.$slug.'-display-token',
            'created_by' => $admin->id,
        ]);

        return [$organization, $admin, $kelas, $quiz, $session, $participant];
    }

    /** @return array{Organization, User, Kelas, User} */
    private function context(string $slug): array
    {
        $organization = Organization::create([
            'name' => 'Organisasi '.$slug,
            'slug' => 'tools-'.$slug,
            'type' => 'education',
            'timezone' => 'Asia/Singapore',
            'is_active' => true,
        ]);
        $admin = $this->user('Admin '.$slug, 'admin_tools_'.$slug, 'admin');
        $participant = $this->user('Peserta '.$slug, 'peserta_tools_'.$slug, 'peserta');
        $organization->users()->attach([
            $admin->id => ['role' => 'owner', 'status' => 'active'],
            $participant->id => ['role' => 'participant', 'status' => 'active'],
        ]);
        $kelas = Kelas::create([
            'organization_id' => $organization->id,
            'nama' => 'Kelas '.$slug,
            'pin' => strtoupper(substr(hash('sha256', $slug), 0, 6)),
            'created_by' => $admin->id,
        ]);
        $kelas->peserta()->attach($participant->id);

        return [$organization, $admin, $kelas, $participant];
    }

    private function addQuestion(Quiz $quiz, Organization $organization): void
    {
        Question::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'teks' => 'Berapa hasil 2 + 2?',
            'tipe' => 'pilihan_ganda',
            'durasi_detik' => 30,
            'bobot' => 10,
        ]);
    }

    /** @param list<string> $tools */
    private function sessionPayload(QuizSession $session, array $tools): array
    {
        return [
            'quiz_id' => $session->quiz_id,
            'kelas_id' => $session->kelas_id,
            'nama' => $session->nama,
            'mode' => $session->mode,
            'jeda_detik' => 5,
            'timing_mode' => 'duration',
            'exam_tools_present' => 1,
            'exam_tools' => $tools,
        ];
    }

    private function asAdmin(User $admin, Organization $organization): static
    {
        return $this->actingAs($admin)->withSession($this->authSession($organization, $admin));
    }

    private function user(string $name, string $username, string $role): User
    {
        return User::create([
            'nama' => $name,
            'username' => $username,
            'password' => Hash::make('password123'),
            'role' => $role,
            'current_session_token' => $username.'-token',
        ]);
    }

    private function authSession(Organization $organization, User $user): array
    {
        return [
            'auth_token' => $user->current_session_token,
            'organization_id' => $organization->id,
        ];
    }
}
