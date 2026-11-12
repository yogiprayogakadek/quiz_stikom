<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\Organization;
use App\Models\ParticipantFeedback;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ParticipantFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_peserta_melihat_dan_mengirim_masukan_setelah_ujian_pertama(): void
    {
        [$organization, $admin, $participant, $session] = $this->dasar('peserta_masukan');
        $attempt = $this->attempt($participant, $session);

        $this->actingAs($participant)
            ->withSession(['auth_token' => $participant->current_session_token, 'organization_id' => $organization->id])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertSee('Bagaimana pengalaman ujianmu?');

        $this->actingAs($participant)
            ->withSession(['auth_token' => $participant->current_session_token, 'organization_id' => $organization->id])
            ->post(route('peserta.attempt.feedback', $attempt), [
                'rating' => 5,
                'message' => 'Materi jelas dan mudah diikuti.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('participant_feedbacks', [
            'attempt_id' => $attempt->id,
            'organization_id' => $organization->id,
            'user_id' => $participant->id,
            'status' => ParticipantFeedback::STATUS_SUBMITTED,
            'rating' => 5,
        ]);

        $this->actingAs($participant)
            ->withSession(['auth_token' => $participant->current_session_token, 'organization_id' => $organization->id])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertDontSee('Bagaimana pengalaman ujianmu?');
    }

    public function test_lewati_menyimpan_status_dismissed_tanpa_rating(): void
    {
        [$organization, $admin, $participant, $session] = $this->dasar('peserta_lewati');
        $attempt = $this->attempt($participant, $session);

        $this->actingAs($participant)
            ->withSession(['auth_token' => $participant->current_session_token, 'organization_id' => $organization->id])
            ->post(route('peserta.attempt.feedback', $attempt), ['action' => 'dismiss'])
            ->assertRedirect();

        $this->assertDatabaseHas('participant_feedbacks', [
            'attempt_id' => $attempt->id,
            'organization_id' => $organization->id,
            'user_id' => $participant->id,
            'status' => ParticipantFeedback::STATUS_DISMISSED,
            'rating' => null,
        ]);

        $this->actingAs($participant)
            ->withSession(['auth_token' => $participant->current_session_token, 'organization_id' => $organization->id])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertDontSee('Bagaimana pengalaman ujianmu?');
    }

    public function test_hanya_attempt_pertama_yang_memunculkan_masukan(): void
    {
        [$organization, $admin, $participant, $firstSession] = $this->dasar('peserta_pertama');
        $secondSession = $this->quizSession($organization, $admin, 'Sesi Kedua');

        $first = $this->attempt($participant, $firstSession, now()->subMinute()->getTimestampMs());
        $second = $this->attempt($participant, $secondSession, now()->getTimestampMs());

        $this->actingAs($participant)
            ->withSession(['auth_token' => $participant->current_session_token, 'organization_id' => $organization->id])
            ->get(route('peserta.attempt.result', $second))
            ->assertOk()
            ->assertDontSee('Bagaimana pengalaman ujianmu?');

        $this->actingAs($participant)
            ->withSession(['auth_token' => $participant->current_session_token, 'organization_id' => $organization->id])
            ->get(route('peserta.attempt.result', $first))
            ->assertOk()
            ->assertSee('Bagaimana pengalaman ujianmu?');
    }

    public function test_kuis_percobaan_tidak_memunculkan_masukan(): void
    {
        [$organization, $admin, $participant] = $this->dasar('peserta_percobaan');
        $session = $this->quizSession($organization, $admin, 'Kuis Percobaan', true);
        $attempt = $this->attempt($participant, $session);

        $this->actingAs($participant)
            ->withSession(['auth_token' => $participant->current_session_token, 'organization_id' => $organization->id])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertDontSee('Bagaimana pengalaman ujianmu?');
    }

    public function test_master_melihat_masukan_anonim_dari_semua_workspace(): void
    {
        [$organization, $admin, $participant, $session] = $this->dasar('peserta_rahasia');
        $attempt = $this->attempt($participant, $session);

        ParticipantFeedback::create([
            'attempt_id' => $attempt->id,
            'organization_id' => $organization->id,
            'user_id' => $participant->id,
            'status' => ParticipantFeedback::STATUS_SUBMITTED,
            'rating' => 4,
            'message' => 'Tampilan ujian nyaman dipakai.',
            'responded_at' => now(),
        ]);

        $master = User::create([
            'nama' => 'Master Masukan',
            'username' => 'master_masukan',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_platform_admin' => true,
            'current_session_token' => 'master-masukan-token',
        ]);

        $this->actingAs($master)
            ->withSession(['auth_token' => $master->current_session_token])
            ->get(route('master.feedback.index'))
            ->assertOk()
            ->assertSee('Kotak masuk anonim')
            ->assertSee($organization->name)
            ->assertSee('Tampilan ujian nyaman dipakai.')
            ->assertSee('Anonim')
            ->assertDontSee('Peserta peserta_rahasia');
    }

    private function dasar(string $username): array
    {
        $organization = Organization::create([
            'name' => 'Kampus Masukan',
            'slug' => 'kampus-masukan',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin Masukan',
            'username' => 'admin_masukan',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-masukan-token',
        ]);

        $participant = User::create([
            'nama' => 'Peserta '.$username,
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => 'peserta',
            'current_session_token' => 'token-'.$username,
        ]);

        $admin->organizations()->syncWithoutDetaching([
            $organization->id => ['role' => 'admin', 'status' => 'active'],
        ]);
        $participant->organizations()->syncWithoutDetaching([
            $organization->id => ['role' => 'participant', 'status' => 'active'],
        ]);

        $session = $this->quizSession($organization, $admin, 'Sesi Masukan');

        return [$organization, $admin, $participant, $session];
    }

    private function quizSession(Organization $organization, User $admin, string $nama, bool $percobaan = false): QuizSession
    {
        $kelas = Kelas::query()->firstOrCreate(
            ['nama' => 'Kelas Masukan'],
            [
                'organization_id' => $organization->id,
                'pin' => 'MASKAN',
                'created_by' => $admin->id,
            ],
        );

        $quiz = Quiz::create([
            'organization_id' => $organization->id,
            'kelas_id' => $kelas->id,
            'judul' => $percobaan ? 'Kuis Percobaan' : 'Kuis Masukan',
            'created_by' => $admin->id,
            'is_percobaan' => $percobaan,
        ]);

        return QuizSession::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => $nama,
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'token-'.uniqid(),
            'created_by' => $admin->id,
        ]);
    }

    private function attempt(User $participant, QuizSession $session, ?int $selesaiAt = null): Attempt
    {
        return Attempt::create([
            'organization_id' => $session->organization_id,
            'user_id' => $participant->id,
            'quiz_session_id' => $session->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'selesai_at' => $selesaiAt ?? now()->getTimestampMs(),
            'skor_total' => 100,
            'nilai' => 100,
        ]);
    }
}
