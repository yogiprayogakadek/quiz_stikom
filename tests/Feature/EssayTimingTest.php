<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EssayTimingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sesi_menyimpan_durasi_total_kustom(): void
    {
        [$organization, $admin, $quiz] = $this->dasar();

        $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->post(route('admin.sessions.store'), [
                'quiz_id' => $quiz->id,
                'nama' => 'Sesi Essay 100 Menit',
                'mode' => 'acak',
                'jeda_detik' => 5,
                'durasi_total_menit' => 100,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quiz_sessions', [
            'nama' => 'Sesi Essay 100 Menit',
            'durasi_total_detik' => 6000,
        ]);
    }

    public function test_sesi_update_menyimpan_durasi_total(): void
    {
        [$organization, $admin, $quiz] = $this->dasar();

        $session = QuizSession::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Sebelum Update',
            'mode' => 'acak',
            'status' => 'draft',
            'display_token' => 'token-sebelum-update',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->put(route('admin.sessions.update', $session), [
                'quiz_id' => $quiz->id,
                'nama' => 'Sesi Setelah Update',
                'mode' => 'acak',
                'jeda_detik' => 5,
                'durasi_total_menit' => 80,
            ])
            ->assertRedirect();

        $this->assertSame(4800, $session->fresh()->durasi_total_detik);
    }

    public function test_sesi_menolak_kuis_campuran_pg_dan_essay(): void
    {
        [$organization, $admin, $quiz] = $this->dasar();
        $this->buatSoal($quiz, 'Soal PG', 60);

        Question::create([
            'quiz_id' => $quiz->id,
            'organization_id' => $organization->id,
            'teks' => 'Soal essay',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);

        $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->post(route('admin.sessions.store'), [
                'quiz_id' => $quiz->id,
                'nama' => 'Sesi Campuran',
                'mode' => 'acak',
                'jeda_detik' => 5,
            ])
            ->assertSessionHasErrors('quiz_id');

        $this->assertDatabaseMissing('quiz_sessions', ['nama' => 'Sesi Campuran']);
    }

    public function test_sesi_serempak_menolak_kuis_essay_saat_dibuat(): void
    {
        [$organization, $admin, $quiz] = $this->dasar();

        Question::create([
            'quiz_id' => $quiz->id,
            'organization_id' => $organization->id,
            'teks' => 'Soal essay',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);

        $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->post(route('admin.sessions.store'), [
                'quiz_id' => $quiz->id,
                'nama' => 'Sesi Serempak Essay',
                'mode' => 'serempak',
                'jeda_detik' => 5,
            ])
            ->assertSessionHasErrors('mode');

        $this->assertDatabaseMissing('quiz_sessions', ['nama' => 'Sesi Serempak Essay']);
    }

    public function test_sesi_essay_tanpa_durasi_total_memakai_100_menit(): void
    {
        [$organization, $admin, $quiz] = $this->dasar();

        $essay = Question::create([
            'quiz_id' => $quiz->id,
            'organization_id' => $organization->id,
            'teks' => 'Jelaskan perbedaan HTTP dan HTTPS.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);

        $this->assertSame($organization->id, $essay->organization_id);
        $this->assertTrue($quiz->questions()->where('tipe', 'essay')->exists());

        $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->post(route('admin.sessions.store'), [
                'quiz_id' => $quiz->id,
                'nama' => 'Sesi Essay Default',
                'mode' => 'acak',
                'jeda_detik' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quiz_sessions', [
            'nama' => 'Sesi Essay Default',
            'durasi_total_detik' => 6000,
        ]);
    }

    public function test_mulai_acak_memakai_durasi_total_sesi(): void
    {
        [$organization, $admin, $quiz] = $this->dasar();
        $this->buatSoal($quiz, 'Soal satu', 60);
        $this->buatSoal($quiz, 'Soal dua', 120);

        $session = QuizSession::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Essay Global',
            'mode' => 'acak',
            'status' => 'berjalan',
            'durasi_total_detik' => 6000,
            'display_token' => 'token-essay-global',
            'created_by' => $admin->id,
        ]);

        $peserta = $this->peserta($organization, 'peserta-essay-global');
        $attempt = Attempt::create([
            'organization_id' => $organization->id,
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'menunggu',
            'bergabung_at' => now(),
        ]);

        $attempt = app(QuizEngine::class)->mulaiAcak($attempt);

        $this->assertSame(6000 * 1000, $attempt->batas_akhir_at - $attempt->mulai_at);
    }

    public function test_mulai_acak_tanpa_durasi_total_memakai_jumlah_durasi_soal(): void
    {
        [$organization, $admin, $quiz] = $this->dasar();
        $this->buatSoal($quiz, 'Soal satu', 60);
        $this->buatSoal($quiz, 'Soal dua', 120);

        $session = QuizSession::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi PG Global',
            'mode' => 'acak',
            'status' => 'berjalan',
            'display_token' => 'token-pg-global',
            'created_by' => $admin->id,
        ]);

        $peserta = $this->peserta($organization, 'peserta-pg-global');
        $attempt = Attempt::create([
            'organization_id' => $organization->id,
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'menunggu',
            'bergabung_at' => now(),
        ]);

        $attempt = app(QuizEngine::class)->mulaiAcak($attempt);

        $this->assertSame(180 * 1000, $attempt->batas_akhir_at - $attempt->mulai_at);
    }

    public function test_soal_essay_bisa_disimpan_tanpa_durasi_soal(): void
    {
        [$organization, $admin, $quiz] = $this->dasar();

        $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'teks' => 'Jelaskan perlindungan data saat transit.',
                'tipe' => 'essay',
                'bobot' => 10,
            ])
            ->assertRedirect();

        $question = Question::where('teks', 'Jelaskan perlindungan data saat transit.')->firstOrFail();
        $this->assertSame(120, $question->durasi_detik);
    }

    /**
     * @return array{0: Organization, 1: User, 2: Quiz}
     */
    private function dasar(): array
    {
        $organization = Organization::create([
            'name' => 'Kampus Essay Timing',
            'slug' => 'kampus-essay-timing',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin Essay Timing',
            'username' => 'admin-essay-timing',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-essay-timing-token',
        ]);

        $admin->organizations()->syncWithoutDetaching([
            $organization->id => ['role' => 'admin', 'status' => 'active'],
        ]);

        $quiz = Quiz::create([
            'organization_id' => $organization->id,
            'judul' => 'Kuis Essay Timing',
            'created_by' => $admin->id,
        ]);

        return [$organization, $admin, $quiz];
    }

    private function peserta(Organization $organization, string $username): User
    {
        $user = User::create([
            'nama' => 'Peserta '.$username,
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => 'peserta',
            'current_session_token' => 'token-'.$username,
        ]);

        $user->organizations()->syncWithoutDetaching([
            $organization->id => ['role' => 'participant', 'status' => 'active'],
        ]);

        return $user;
    }

    private function buatSoal(Quiz $quiz, string $teks, int $durasi): Question
    {
        return Question::create([
            'quiz_id' => $quiz->id,
            'organization_id' => $quiz->organization_id,
            'teks' => $teks,
            'tipe' => 'pilihan_ganda',
            'bobot' => 1,
            'durasi_detik' => $durasi,
        ]);
    }
}
