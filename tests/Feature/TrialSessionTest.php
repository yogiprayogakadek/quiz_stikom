<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\Option;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionMedia;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class TrialSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_membuat_snapshot_simulasi_dengan_peserta_terpilih(): void
    {
        Storage::fake('local');
        [$organization, $admin, $selected, $unselected, $quiz, $session] = $this->scenario();
        $sourceBefore = $quiz->questions()->where('tipe', 'essay')->firstOrFail();
        $sourcePath = 'question-media/'.$organization->id.'/source.png';
        Storage::disk('local')->put($sourcePath, 'image');
        $sourceMedia = $sourceBefore->media()->create([
            'organization_id' => $organization->id,
            'path' => $sourcePath,
            'mime_type' => 'image/png',
            'size_bytes' => 5,
            'original_name' => 'source.png',
        ]);
        $sourceBefore->update(['content_blocks_json' => [[
            'type' => 'image',
            'media_id' => $sourceMedia->id,
            'alt' => 'Diagram fotosintesis',
            'caption' => '',
            'ai_context' => 'Alur fotosintesis.',
        ]]]);

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->post(route('admin.sessions.trial', $session), [
                'trial_participant_ids' => [$selected->id],
            ])
            ->assertRedirect();

        $trialSession = QuizSession::query()->whereKeyNot($session->id)->firstOrFail();
        $trialQuiz = $trialSession->quiz()->with('questions.options')->firstOrFail();

        $this->assertTrue($trialQuiz->is_percobaan);
        $this->assertSame($quiz->id, $trialQuiz->source_quiz_id);
        $this->assertSame($quiz->kelas_id, $trialQuiz->kelas_id);
        $this->assertSame($quiz->jumlah_soal, $trialQuiz->jumlah_soal);
        $this->assertSame($quiz->acak_opsi, $trialQuiz->acak_opsi);
        $this->assertSame($quiz->auto_grade_essay, $trialQuiz->auto_grade_essay);
        $this->assertCount(2, $trialQuiz->questions);
        $this->assertSame(2, $trialQuiz->questions->firstWhere('tipe', 'pilihan_ganda')->options->count());

        $sourceEssay = $quiz->questions()->where('tipe', 'essay')->firstOrFail();
        $trialEssay = $trialQuiz->questions->firstWhere('tipe', 'essay');
        $this->assertNotSame($sourceEssay->id, $trialEssay->id);
        $this->assertSame($sourceEssay->petunjuk_json, $trialEssay->petunjuk_json);
        $this->assertSame($sourceEssay->sub_soal_json, $trialEssay->sub_soal_json);
        $this->assertSame($sourceEssay->rubrik_essay, $trialEssay->rubrik_essay);
        $this->assertSame($sourceEssay->kunci_essay, $trialEssay->kunci_essay);
        $trialMediaId = $trialEssay->content_blocks_json[0]['media_id'];
        $this->assertNotSame($sourceMedia->id, $trialMediaId);
        $trialMedia = QuestionMedia::findOrFail($trialMediaId);
        Storage::disk('local')->assertExists($trialMedia->path);

        $this->assertNull($trialSession->kelas_id);
        $this->assertSame('draft', $trialSession->status);
        $this->assertSame($session->mode, $trialSession->mode);
        $this->assertSame($session->durasi_total_detik, $trialSession->durasi_total_detik);
        $this->assertSame($session->max_pelanggaran, $trialSession->max_pelanggaran);
        $this->assertSame($session->pelanggaran_action, $trialSession->pelanggaran_action);
        $this->assertSame($session->pelanggaran_penalty_seconds, $trialSession->pelanggaran_penalty_seconds);
        $this->assertSame($session->clipboard_policy, $trialSession->clipboard_policy);
        $this->assertSame($session->timing_mode, $trialSession->timing_mode);
        $this->assertSame([$selected->id], $trialSession->allowedParticipants()->pluck('users.id')->all());
        $this->assertFalse($trialSession->isUserAllowed($unselected));
        $this->assertDatabaseCount('attempts', 0);

        $trialEssay->update(['teks' => 'Soal simulasi diubah']);
        $this->assertSame('Jelaskan fotosintesis.', $sourceEssay->fresh()->teks);
        $this->assertFalse($quiz->fresh()->isLocked());
    }

    public function test_simulasi_hanya_dapat_diakses_peserta_yang_dipilih(): void
    {
        [$organization, $admin, $selected, $unselected, $sourceQuiz, $session] = $this->scenario();

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->post(route('admin.sessions.trial', $session), [
                'trial_participant_ids' => [$selected->id],
            ]);

        $trialSession = QuizSession::query()->whereKeyNot($session->id)->firstOrFail();

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->post(route('admin.sessions.lobby', $trialSession))
            ->assertRedirect();

        $this->assertFalse($sourceQuiz->fresh()->isLocked());

        $this->actingAs($selected)
            ->withSession($this->authSession($organization, $selected))
            ->getJson(route('peserta.dashboard.state'))
            ->assertOk()
            ->assertJsonPath('sesi_tersedia.0.id', $trialSession->id);

        $this->actingAs($unselected)
            ->withSession($this->authSession($organization, $unselected))
            ->getJson(route('peserta.dashboard.state'))
            ->assertOk()
            ->assertJsonCount(0, 'sesi_tersedia');

        $this->actingAs($selected)
            ->withSession($this->authSession($organization, $selected))
            ->post(route('peserta.session.join', $trialSession))
            ->assertRedirect();

        $this->actingAs($unselected)
            ->withSession($this->authSession($organization, $unselected))
            ->post(route('peserta.session.join', $trialSession))
            ->assertSessionHasErrors('gabung');

        $this->assertDatabaseHas('attempts', [
            'quiz_session_id' => $trialSession->id,
            'user_id' => $selected->id,
        ]);
        $this->assertDatabaseMissing('attempts', [
            'quiz_session_id' => $trialSession->id,
            'user_id' => $unselected->id,
        ]);
    }

    public function test_simulasi_menolak_status_sumber_dan_peserta_di_luar_workspace(): void
    {
        [$organization, $admin, $selected, , , $session] = $this->scenario();
        [$otherOrganization, , $outsider] = $this->organizationUsers('other');
        $inactive = $this->user('Penguji nonaktif', 'penguji_nonaktif', 'peserta');
        $organization->users()->attach($inactive->id, ['role' => 'participant', 'status' => 'inactive']);

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->post(route('admin.sessions.trial', $session), [
                'trial_participant_ids' => [$inactive->id],
            ])
            ->assertSessionHasErrors('trial_participant_ids.0');

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->post(route('admin.sessions.trial', $session), [
                'trial_participant_ids' => [$outsider->id],
            ])
            ->assertSessionHasErrors('trial_participant_ids.0');

        $session->update(['status' => 'lobby']);

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->post(route('admin.sessions.trial', $session), [
                'trial_participant_ids' => [$selected->id],
            ])
            ->assertSessionHasErrors('trial');

        $this->assertDatabaseCount('quizzes', 1);
        $this->assertDatabaseCount('quiz_sessions', 1);
        $this->assertNotSame($organization->id, $otherOrganization->id);
    }

    public function test_pembuatan_simulasi_rollback_jika_snapshot_gagal(): void
    {
        [$organization, $admin, $selected, , , $session] = $this->scenario();
        $quizCount = Quiz::count();
        $sessionCount = QuizSession::count();
        $questionCount = Question::count();
        $optionCount = Option::count();

        Option::creating(fn () => throw new RuntimeException('Gagal menyalin opsi.'));

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)
                ->withSession($this->authSession($organization, $admin))
                ->post(route('admin.sessions.trial', $session), [
                    'trial_participant_ids' => [$selected->id],
                ]);
            $this->fail('Penyalinan seharusnya gagal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Gagal menyalin opsi.', $exception->getMessage());
        }

        $this->assertDatabaseCount('quizzes', $quizCount);
        $this->assertDatabaseCount('quiz_sessions', $sessionCount);
        $this->assertDatabaseCount('questions', $questionCount);
        $this->assertDatabaseCount('options', $optionCount);
    }

    public function test_ruang_kendali_dan_laporan_menampilkan_alur_simulasi(): void
    {
        [$organization, $admin, $selected, , $quiz, $session] = $this->scenario();

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->get(route('admin.sessions.show', $session))
            ->assertOk()
            ->assertSee('Buat simulasi')
            ->assertSee('Cari peserta')
            ->assertSee('data-progress-message="Membuat simulasi ujian..."', false);

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->post(route('admin.sessions.trial', $session), [
                'trial_participant_ids' => [$selected->id],
            ]);

        $trialSession = QuizSession::query()->whereKeyNot($session->id)->firstOrFail();
        $trialQuizId = $trialSession->quiz_id;
        $trialSession->update(['status' => 'selesai']);

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->get(route('admin.reports.session', $trialSession))
            ->assertOk()
            ->assertSee('Buka ujian asli')
            ->assertSee(route('admin.quizzes.edit', $quiz), false);

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->delete(route('admin.sessions.destroy', $trialSession))
            ->assertRedirect(route('admin.sessions.index'));

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id]);
        $this->assertDatabaseMissing('quizzes', ['id' => $trialQuizId]);
        $this->assertDatabaseMissing('quiz_sessions', ['id' => $trialSession->id]);
    }

    public function test_sesi_nyata_dengan_attempt_peserta_tidak_dapat_dihapus(): void
    {
        [$organization, $admin, $selected, , , $session] = $this->scenario();
        $attempt = Attempt::create([
            'organization_id' => $organization->id,
            'quiz_session_id' => $session->id,
            'user_id' => $selected->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'mulai_at' => now()->subMinutes(30)->getTimestampMs(),
            'selesai_at' => now()->getTimestampMs(),
        ]);

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->delete(route('admin.sessions.destroy', $session))
            ->assertSessionHasErrors([
                'hapus' => 'Sesi ini menjadi bagian dari riwayat peserta dan harus dipertahankan.',
            ]);

        $this->assertDatabaseHas('quiz_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('attempts', ['id' => $attempt->id]);
    }

    public function test_sesi_nyata_tanpa_attempt_peserta_tetap_dapat_dihapus(): void
    {
        [$organization, $admin, , , , $session] = $this->scenario();

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->delete(route('admin.sessions.destroy', $session))
            ->assertRedirect(route('admin.sessions.index'));

        $this->assertDatabaseMissing('quiz_sessions', ['id' => $session->id]);
    }

    public function test_simulasi_dengan_attempt_tetap_dapat_dihapus_dan_sumbernya_utuh(): void
    {
        [$organization, $admin, $selected, , $quiz, $session] = $this->scenario();

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->post(route('admin.sessions.trial', $session), [
                'trial_participant_ids' => [$selected->id],
            ]);

        $trialSession = QuizSession::query()->whereKeyNot($session->id)->firstOrFail();
        $trialQuizId = $trialSession->quiz_id;
        $attempt = Attempt::create([
            'organization_id' => $organization->id,
            'quiz_session_id' => $trialSession->id,
            'user_id' => $selected->id,
            'status' => 'selesai',
            'bergabung_at' => now(),
            'mulai_at' => now()->subMinutes(10)->getTimestampMs(),
            'selesai_at' => now()->getTimestampMs(),
        ]);

        $this->actingAs($admin)
            ->withSession($this->authSession($organization, $admin))
            ->delete(route('admin.sessions.destroy', $trialSession))
            ->assertRedirect(route('admin.sessions.index'));

        $this->assertDatabaseMissing('quiz_sessions', ['id' => $trialSession->id]);
        $this->assertDatabaseMissing('attempts', ['id' => $attempt->id]);
        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id]);
        $this->assertDatabaseMissing('quizzes', ['id' => $trialQuizId]);
    }

    private function scenario(): array
    {
        [$organization, $admin, $selected, $unselected] = $this->organizationUsers('alpha');

        $kelas = Kelas::create([
            'organization_id' => $organization->id,
            'nama' => 'Kelas Alpha',
            'pin' => 'ALPHA1',
            'created_by' => $admin->id,
        ]);

        $quiz = Quiz::create([
            'organization_id' => $organization->id,
            'kelas_id' => $kelas->id,
            'judul' => 'Ujian Alpha',
            'jumlah_soal' => 2,
            'acak_opsi' => true,
            'auto_grade_essay' => true,
            'is_percobaan' => false,
            'created_by' => $admin->id,
        ]);

        $pg = Question::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'teks' => 'Dua tambah dua?',
            'tipe' => 'pilihan_ganda',
            'durasi_detik' => 60,
            'bobot' => 40,
            'pembahasan' => 'Hasilnya empat.',
        ]);
        $pg->options()->createMany([
            ['teks' => 'Empat', 'is_correct' => true],
            ['teks' => 'Lima', 'is_correct' => false],
        ]);

        Question::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'teks' => 'Jelaskan fotosintesis.',
            'tipe' => 'essay',
            'durasi_detik' => 300,
            'bobot' => 60,
            'pembahasan' => 'Fotosintesis mengubah energi cahaya.',
            'kunci_essay' => 'Cahaya, air, dan karbon dioksida.',
            'petunjuk_json' => ['Gunakan istilah ilmiah.'],
            'sub_soal_json' => [
                ['key' => 'a', 'parent_key' => null, 'label' => 'A', 'teks' => 'Sebutkan bahannya.', 'petunjuk' => []],
                ['key' => 'a1', 'parent_key' => 'a', 'label' => '1', 'teks' => 'Sebutkan air.', 'petunjuk' => []],
            ],
            'rubrik_essay' => [['label' => 'Ketepatan', 'points' => 60]],
        ]);

        $session = QuizSession::create([
            'organization_id' => $organization->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi Ujian Alpha',
            'mode' => 'acak',
            'acak_opsi' => true,
            'tampilkan_pembahasan' => false,
            'pembahasan_mode' => 'otomatis',
            'leaderboard_live' => false,
            'max_peserta' => 10,
            'max_pelanggaran' => 4,
            'durasi_peringatan' => 45,
            'pelanggaran_action' => 'reduce_time',
            'pelanggaran_penalty_seconds' => 90,
            'clipboard_policy' => 'block',
            'jeda_detik' => 7,
            'durasi_total_detik' => 3600,
            'timing_mode' => 'duration',
            'status' => 'draft',
            'display_token' => 'source-alpha-token',
            'created_by' => $admin->id,
        ]);

        return [$organization, $admin, $selected, $unselected, $quiz, $session];
    }

    private function organizationUsers(string $slug): array
    {
        $organization = Organization::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Singapore',
            'is_active' => true,
        ]);

        $admin = $this->user('Admin '.$slug, 'admin_'.$slug, 'admin');
        $selected = $this->user('Penguji '.$slug, 'penguji_'.$slug, 'peserta');
        $unselected = $this->user('Peserta '.$slug, 'peserta_'.$slug, 'peserta');

        $organization->users()->attach([
            $admin->id => ['role' => 'owner', 'status' => 'active'],
            $selected->id => ['role' => 'participant', 'status' => 'active'],
            $unselected->id => ['role' => 'participant', 'status' => 'active'],
        ]);

        return [$organization, $admin, $selected, $unselected];
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
