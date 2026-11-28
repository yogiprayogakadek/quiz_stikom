<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Option;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MixedQuizSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_siklus_penuh_kuis_campuran_pg_dan_essay_di_mode_acak(): void
    {
        $organization = Organization::create([
            'name' => 'Kampus Campuran',
            'slug' => 'kampus-campuran',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Dosen Campuran',
            'username' => 'dosen-campuran',
            'email' => 'dosen-campuran@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => 'token-dosen-campuran',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $peserta = User::create([
            'nama' => 'Mahasiswa Campuran',
            'username' => 'mhs-campuran',
            'email' => 'mhs-campuran@example.test',
            'password' => Hash::make('password123'),
            'role' => 'peserta',
            'current_session_token' => 'token-mhs-campuran',
        ]);
        $peserta->forceFill(['email_verified_at' => now()])->save();

        $organization->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'admin', 'status' => 'active'],
            $peserta->id => ['role' => 'participant', 'status' => 'active'],
        ]);

        $quiz = Quiz::create([
            'organization_id' => $organization->id,
            'judul' => 'UAS Keamanan Jaringan',
            'acak_opsi' => false,
            'created_by' => $admin->id,
        ]);

        $pg = Question::create([
            'quiz_id' => $quiz->id,
            'organization_id' => $organization->id,
            'teks' => 'Port standar HTTPS adalah?',
            'tipe' => 'pilihan_ganda',
            'bobot' => 40,
            'durasi_detik' => 60,
        ]);
        $opsiBenar = Option::create(['question_id' => $pg->id, 'teks' => '443', 'is_correct' => true]);
        Option::create(['question_id' => $pg->id, 'teks' => '80', 'is_correct' => false]);

        $essay = Question::create([
            'quiz_id' => $quiz->id,
            'organization_id' => $organization->id,
            'teks' => 'Jelaskan cara kerja TLS handshake secara ringkas.',
            'tipe' => 'essay',
            'bobot' => 60,
            'durasi_detik' => 180,
        ]);

        $adminSession = [
            'auth_token' => $admin->current_session_token,
            'organization_id' => $organization->id,
        ];
        $pesertaSession = [
            'auth_token' => $peserta->current_session_token,
            'organization_id' => $organization->id,
        ];

        $this->actingAs($admin)
            ->withSession($adminSession)
            ->post(route('admin.sessions.store'), [
                'quiz_id' => $quiz->id,
                'nama' => 'Sesi UAS Campuran',
                'mode' => 'acak',
                'jeda_detik' => 5,
                'durasi_total_menit' => 90,
            ])
            ->assertRedirect();

        $session = QuizSession::where('nama', 'Sesi UAS Campuran')->firstOrFail();
        $session->allowedParticipants()->sync([$peserta->id]);

        $this->actingAs($admin)
            ->withSession($adminSession)
            ->post(route('admin.sessions.lobby', $session))
            ->assertRedirect();

        $this->actingAs($admin)
            ->withSession($adminSession)
            ->post(route('admin.sessions.start', $session), ['peserta_ids' => [$peserta->id]])
            ->assertRedirect();

        $this->actingAs($peserta)
            ->withSession($pesertaSession)
            ->post(route('peserta.session.join', $session))
            ->assertRedirect();

        $this->actingAs($peserta)
            ->withSession($pesertaSession)
            ->post(route('peserta.attempt.start', $session->id))
            ->assertRedirect();

        $attempt = Attempt::where('quiz_session_id', $session->id)
            ->where('user_id', $peserta->id)
            ->firstOrFail();

        $this->actingAs($peserta)
            ->withSession($pesertaSession)
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $pg->id,
                'option_id' => $opsiBenar->id,
            ])
            ->assertOk();

        $this->actingAs($peserta)
            ->withSession($pesertaSession)
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'jawaban_teks' => 'Client dan server bertukar parameter kriptografi lalu memverifikasi sertifikat.',
            ])
            ->assertOk();

        $this->actingAs($peserta)
            ->withSession($pesertaSession)
            ->post(route('peserta.attempt.finish', $attempt))
            ->assertRedirect(route('peserta.attempt.result', $attempt));

        $attempt->refresh();
        $this->assertSame('selesai', $attempt->status);
        $this->assertSame(1, $attempt->jumlah_benar);
        $this->assertSame(40, $attempt->poin_total);
        $this->assertSame(100, $attempt->poin_maksimal);
        $this->assertSame(40, $attempt->skor_total);

        $riwayat = $this->actingAs($peserta)
            ->withSession($pesertaSession)
            ->get(route('peserta.history.index'))
            ->assertOk()
            ->viewData('riwayat');
        $this->assertSame(1, $riwayat->first()->essay_pending_count);

        $this->actingAs($admin)
            ->withSession($adminSession)
            ->post(route('admin.sessions.close', $session))
            ->assertRedirect();

        $this->actingAs($admin)
            ->withSession($adminSession)
            ->post(route('admin.sessions.nilai', $session), ['status' => 'ditampilkan'])
            ->assertSessionHasErrors('nilai');

        $this->actingAs($admin)
            ->withSession($adminSession)
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$essay->id => 50],
                'catatan' => [$essay->id => 'Penjelasan runtut dan akurat.'],
            ])
            ->assertRedirect();

        $attempt->refresh();
        $this->assertSame(90, $attempt->poin_total);
        $this->assertSame(100, $attempt->poin_maksimal);
        $this->assertSame(90, $attempt->skor_total);

        $this->actingAs($admin)
            ->withSession($adminSession)
            ->post(route('admin.sessions.nilai', $session), ['status' => 'ditampilkan'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($session->fresh()->nilaiTampil());
    }
}
