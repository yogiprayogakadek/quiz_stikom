<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PesanPengajarTest extends TestCase
{
    use RefreshDatabase;

    public function test_pesan_aktif_hanya_yang_belum_ditutup(): void
    {
        [, , $attempts] = $this->sesiBerjalan(1);
        $attempt = $attempts[0];
        $aktif = SessionMessage::create([
            'quiz_session_id' => $attempt->quiz_session_id,
            'attempt_id' => $attempt->id,
            'user_id' => $attempt->user_id,
            'teks' => 'Sisa waktu 10 menit.',
            'tipe' => 'info',
        ]);
        SessionMessage::create([
            'quiz_session_id' => $attempt->quiz_session_id,
            'attempt_id' => $attempt->id,
            'user_id' => $attempt->user_id,
            'teks' => 'Sudah ditutup.',
            'tipe' => 'info',
            'ditutup_at' => now(),
        ]);

        $hasil = SessionMessage::aktifUntukAttempt($attempt->id);

        $this->assertCount(1, $hasil);
        $this->assertSame($aktif->id, $hasil->first()->id);
        $this->assertSame(['id', 'teks', 'tipe', 'waktu'], array_keys($hasil->first()->untukPeserta()));
    }

    /**
     * Organisasi, admin, sesi acak berjalan, dan attempt pesertanya.
     *
     * @return array{0: User, 1: QuizSession, 2: \Illuminate\Support\Collection<int, Attempt>, 3: Organization}
     */
    private function sesiBerjalan(int $jumlahPeserta = 2): array
    {
        $slug = 'pesan-'.Str::lower(Str::random(6));

        $organization = Organization::create([
            'name' => 'Kampus Pesan',
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin Pesan',
            'username' => 'admin_'.$slug,
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-'.$slug,
        ]);

        $admin->organizations()->syncWithoutDetaching([
            $organization->id => ['role' => 'admin', 'status' => 'active'],
        ]);

        $kelas = Kelas::create([
            'organization_id' => $organization->id,
            'nama' => 'Kelas Pesan',
            'pin' => 'P'.strtoupper(substr(md5($slug), 0, 7)),
            'created_by' => $admin->id,
        ]);

        $quiz = Quiz::create([
            'organization_id' => $organization->id,
            'kelas_id' => $kelas->id,
            'judul' => 'Kuis Pesan',
            'created_by' => $admin->id,
        ]);

        $session = QuizSession::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi Pesan',
            'mode' => 'acak',
            'status' => 'berjalan',
            'display_token' => 'display-'.$slug,
            'created_by' => $admin->id,
        ]);

        $attempts = collect();

        for ($i = 0; $i < $jumlahPeserta; $i++) {
            $peserta = User::create([
                'nama' => 'Peserta '.$i,
                'username' => 'peserta_'.$slug.'_'.$i,
                'password' => Hash::make('password'),
                'role' => 'peserta',
                'current_session_token' => 'token-'.$slug.'-'.$i,
            ]);

            $peserta->organizations()->syncWithoutDetaching([
                $organization->id => ['role' => 'participant', 'status' => 'active'],
            ]);

            $attempts->push(Attempt::create([
                'organization_id' => $organization->id,
                'user_id' => $peserta->id,
                'quiz_session_id' => $session->id,
                'status' => 'berjalan',
                'bergabung_at' => now(),
                'batas_akhir_at' => now()->getTimestampMs() + (10 * 60 * 1000),
            ]));
        }

        return [$admin, $session, $attempts->sortBy('id')->values(), $organization];
    }
}
