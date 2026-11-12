<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardLatestScoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_nilai_terbaru_mengambil_lima_attempt_terakhir_lalu_diurutkan_skor_tertinggi(): void
    {
        $organization = Organization::query()->firstOrFail();
        $admin = User::create([
            'nama' => 'Admin Nilai',
            'username' => 'admin_nilai',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-nilai-token',
        ]);
        $admin->organizations()->syncWithoutDetaching([
            $organization->id => ['role' => 'admin', 'status' => 'active'],
        ]);

        $kelas = Kelas::create([
            'organization_id' => $organization->id,
            'nama' => 'Kelas Nilai',
            'pin' => 'NILAI1',
            'created_by' => $admin->id,
        ]);

        $quiz = Quiz::create([
            'organization_id' => $organization->id,
            'kelas_id' => $kelas->id,
            'judul' => 'Kuis Nilai Terbaru',
            'created_by' => $admin->id,
            'is_percobaan' => false,
        ]);

        $session = QuizSession::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi Nilai Terbaru',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'token-nilai-terbaru',
            'created_by' => $admin->id,
        ]);

        $attempts = [
            ['menit' => 6, 'skor' => 999, 'nilai' => 999, 'username' => 'peserta_lama'],
            ['menit' => 5, 'skor' => 10, 'nilai' => 100, 'username' => 'peserta_lima'],
            ['menit' => 4, 'skor' => 50, 'nilai' => 20, 'username' => 'peserta_empat'],
            ['menit' => 3, 'skor' => 30, 'nilai' => 80, 'username' => 'peserta_tiga'],
            ['menit' => 2, 'skor' => 40, 'nilai' => 30, 'username' => 'peserta_dua'],
            ['menit' => 1, 'skor' => 20, 'nilai' => 90, 'username' => 'peserta_satu'],
        ];

        foreach ($attempts as $data) {
            $participant = User::create([
                'nama' => ucfirst($data['username']),
                'username' => $data['username'],
                'password' => Hash::make('password'),
                'role' => 'peserta',
            ]);

            $attempt = Attempt::create([
                'organization_id' => $organization->id,
                'user_id' => $participant->id,
                'quiz_session_id' => $session->id,
                'status' => 'selesai',
                'bergabung_at' => now(),
                'selesai_at' => now()->getTimestampMs(),
                'skor_total' => $data['skor'],
                'nilai' => $data['nilai'],
            ]);

            DB::table('attempts')->where('id', $attempt->id)->update([
                'updated_at' => now()->subMinutes($data['menit']),
            ]);
        }

        $response = $this->actingAs($admin)
            ->withSession([
                'auth_token' => $admin->current_session_token,
                'organization_id' => $organization->id,
            ])
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('leaderboardTerbaru', function ($leaderboard) {
            return $leaderboard->pluck('skor_total')->all() === [50, 40, 30, 20, 10];
        });
    }
}
