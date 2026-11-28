<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ObjectiveQuestionTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menyimpan_soal_pilihan_majemuk_dan_isian_singkat_serta_pratinjau_pdf(): void
    {
        [$org, $admin, , $kelas] = $this->buatTenant('obj', 'mhs_obj1');

        $quiz = Quiz::create([
            'organization_id' => $org->id,
            'kelas_id' => $kelas->id,
            'judul' => 'Kuis Tipe Objektif Baru',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $org->id])
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'tipe' => 'pilihan_majemuk',
                'teks' => 'Manakah protokol layer transport pada model TCP/IP?',
                'bobot' => 20,
                'durasi_detik' => 45,
                'scoring_mode' => 'partial',
                'opsi' => [
                    ['teks' => 'TCP'],
                    ['teks' => 'IP'],
                    ['teks' => 'UDP'],
                    ['teks' => 'ICMP'],
                ],
                'correct_indices' => [0, 2],
            ])
            ->assertRedirect(route('admin.quizzes.questions.index', $quiz));

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $org->id])
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'tipe' => 'isian_singkat',
                'teks' => 'Tuliskan subnet mask desimal untuk prefix /24!',
                'bobot' => 10,
                'durasi_detik' => 30,
                'accepted_answers' => "255.255.255.0\n/24",
                'case_sensitive' => '0',
            ])
            ->assertRedirect(route('admin.quizzes.questions.index', $quiz));

        $majemuk = Question::where('quiz_id', $quiz->id)->where('tipe', 'pilihan_majemuk')->with('options')->firstOrFail();
        $this->assertSame('partial', $majemuk->answer_config_json['scoring_mode'] ?? null);
        $this->assertSame(['TCP', 'UDP'], $majemuk->options->where('is_correct', true)->pluck('teks')->values()->all());

        $isian = Question::where('quiz_id', $quiz->id)->where('tipe', 'isian_singkat')->with('options')->firstOrFail();
        $this->assertFalse((bool) ($isian->answer_config_json['case_sensitive'] ?? true));
        $this->assertSame(['255.255.255.0', '/24'], $isian->options->where('is_correct', true)->pluck('teks')->values()->all());

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $org->id])
            ->get(route('admin.quizzes.questions.pdf.preview', ['quiz' => $quiz, 'varian' => 'pengajar']))
            ->assertOk()
            ->assertSee('Manakah protokol layer transport')
            ->assertSee('255.255.255.0');
    }

    public function test_penilaian_otomatis_pilihan_majemuk_semua_atau_nol_dan_parsial_serta_isian_singkat_di_mode_acak(): void
    {
        [$org, $admin, $peserta1, $kelas] = $this->buatTenant('acakobj', 'mhs_acak_1');
        $peserta2 = User::create([
            'nama' => 'Mahasiswa Dua',
            'username' => 'mhs_acak_2',
            'password' => Hash::make('password123'),
            'role' => 'peserta',
            'current_session_token' => 'tok-mhs-acak-2',
        ]);
        $org->users()->attach($peserta2->id, ['role' => 'participant', 'status' => 'active']);
        $kelas->peserta()->attach($peserta2->id);

        $quiz = Quiz::create([
            'organization_id' => $org->id,
            'kelas_id' => $kelas->id,
            'judul' => 'Ujian Objektif Akurat',
            'acak_soal' => false,
            'acak_opsi' => false,
            'created_by' => $admin->id,
        ]);

        // Q1: Pilihan Majemuk Semua-atau-Nol (bobot 40)
        $q1 = Question::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'teks' => 'Pilih tepat dua bilangan prima genap atau ganjil terkecil (2 dan 3)',
            'tipe' => 'pilihan_majemuk',
            'durasi_detik' => 45,
            'bobot' => 40,
            'answer_config_json' => ['scoring_mode' => 'all_or_nothing'],
        ]);
        $q1A = $q1->options()->create(['teks' => '2', 'is_correct' => true]);
        $q1B = $q1->options()->create(['teks' => '4', 'is_correct' => false]);
        $q1C = $q1->options()->create(['teks' => '3', 'is_correct' => true]);
        $q1->options()->create(['teks' => '9', 'is_correct' => false]);

        // Q2: Pilihan Majemuk Parsial (bobot 40)
        $q2 = Question::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'teks' => 'Pilih HTTP method yang bersifat idempotent (GET dan PUT)',
            'tipe' => 'pilihan_majemuk',
            'durasi_detik' => 45,
            'bobot' => 40,
            'answer_config_json' => ['scoring_mode' => 'partial'],
        ]);
        $q2A = $q2->options()->create(['teks' => 'GET', 'is_correct' => true]);
        $q2B = $q2->options()->create(['teks' => 'POST', 'is_correct' => false]);
        $q2C = $q2->options()->create(['teks' => 'PUT', 'is_correct' => true]);
        $q2->options()->create(['teks' => 'CONNECT', 'is_correct' => false]);

        // Q3: Isian Singkat (bobot 20)
        $q3 = Question::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'teks' => 'Port default HTTPS adalah...',
            'tipe' => 'isian_singkat',
            'durasi_detik' => 30,
            'bobot' => 20,
            'answer_config_json' => ['case_sensitive' => false],
        ]);
        $q3->options()->createMany([
            ['teks' => '443', 'is_correct' => true],
            ['teks' => 'tcp 443', 'is_correct' => true],
        ]);

        $session = QuizSession::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi Ujian Objektif',
            'mode' => 'acak',
            'status' => 'berjalan',
            'nilai_mode' => 'ditampilkan',
            'pembahasan_mode' => 'terbuka',
            'tampilkan_pembahasan' => true,
            'dibuka_at' => now(),
            'display_token' => Str::random(48),
            'created_by' => $admin->id,
        ]);

        // Peserta 1: Q1 hanya pilih [2] -> 0; Q2 pilih [GET] -> 20; Q3 isi '  TCP  443 ' -> 20 => Total 40
        $attempt1 = $this->mulaiAttempt($peserta1, $org, $session);
        $this->actingAs($peserta1)
            ->withSession(['auth_token' => $peserta1->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('peserta.attempt.answer', $attempt1), [
                'question_id' => $q1->id,
                'option_ids' => [$q1A->id],
            ])
            ->assertOk();

        $this->actingAs($peserta1)
            ->withSession(['auth_token' => $peserta1->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('peserta.attempt.answer', $attempt1), [
                'question_id' => $q2->id,
                'option_ids' => [$q2A->id],
            ])
            ->assertOk();

        $this->actingAs($peserta1)
            ->withSession(['auth_token' => $peserta1->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('peserta.attempt.answer', $attempt1), [
                'question_id' => $q3->id,
                'jawaban_teks' => '  TCP   443  ',
            ])
            ->assertOk();

        $this->actingAs($peserta1)
            ->withSession(['auth_token' => $peserta1->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.attempt.finish', $attempt1))
            ->assertRedirect(route('peserta.attempt.result', $attempt1));

        $attempt1->refresh();
        $this->assertSame(40, $attempt1->poin_total);
        $this->assertSame(40, $attempt1->skor_total);
        $this->assertSame(1, $attempt1->jumlah_benar);

        // Halaman hasil peserta & laporan admin menampilkan tipe baru tanpa error
        $this->actingAs($peserta1)
            ->withSession(['auth_token' => $peserta1->current_session_token, 'organization_id' => $org->id])
            ->get(route('peserta.attempt.result', $attempt1))
            ->assertOk()
            ->assertSee('Pilihan majemuk')
            ->assertSee('Isian singkat');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $org->id])
            ->get(route('admin.reports.attempt', $attempt1))
            ->assertOk()
            ->assertSee('GET');

        // Peserta 2: Q1 pilih [2, 3] -> 40; Q2 pilih [GET, POST] (1 benar - 1 salah = 0); Q3 isi '443' -> 20 => Total 60
        $attempt2 = $this->mulaiAttempt($peserta2, $org, $session);
        $this->actingAs($peserta2)
            ->withSession(['auth_token' => $peserta2->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('peserta.attempt.answer', $attempt2), [
                'question_id' => $q1->id,
                'option_ids' => [$q1A->id, $q1C->id],
            ])
            ->assertOk();

        $this->actingAs($peserta2)
            ->withSession(['auth_token' => $peserta2->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('peserta.attempt.answer', $attempt2), [
                'question_id' => $q2->id,
                'option_ids' => [$q2A->id, $q2B->id],
            ])
            ->assertOk();

        $this->actingAs($peserta2)
            ->withSession(['auth_token' => $peserta2->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('peserta.attempt.answer', $attempt2), [
                'question_id' => $q3->id,
                'jawaban_teks' => '443',
            ])
            ->assertOk();

        $this->actingAs($peserta2)
            ->withSession(['auth_token' => $peserta2->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.attempt.finish', $attempt2));

        $attempt2->refresh();
        $this->assertSame(60, $attempt2->poin_total);
        $this->assertSame(60, $attempt2->skor_total);
        $this->assertSame(2, $attempt2->jumlah_benar);

        unset($q1B, $q2C);
    }

    public function test_pilihan_majemuk_dan_isian_singkat_berjalan_di_mode_serempak(): void
    {
        Queue::fake();
        [$org, $admin, $peserta, $kelas] = $this->buatTenant('serempakobj', 'mhs_srmpk');

        $quiz = Quiz::create([
            'organization_id' => $org->id,
            'kelas_id' => $kelas->id,
            'judul' => 'Serempak Tipe Baru',
            'acak_soal' => false,
            'acak_opsi' => false,
            'created_by' => $admin->id,
        ]);

        $q1 = Question::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'teks' => 'Pilih dua protokol email',
            'tipe' => 'pilihan_majemuk',
            'durasi_detik' => 30,
            'bobot' => 10,
            'answer_config_json' => ['scoring_mode' => 'all_or_nothing'],
        ]);
        $optSmtp = $q1->options()->create(['teks' => 'SMTP', 'is_correct' => true]);
        $optImap = $q1->options()->create(['teks' => 'IMAP', 'is_correct' => true]);
        $q1->options()->create(['teks' => 'FTP', 'is_correct' => false]);

        $q2 = Question::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'teks' => 'Singkatan Domain Name System adalah...',
            'tipe' => 'isian_singkat',
            'durasi_detik' => 30,
            'bobot' => 10,
            'answer_config_json' => ['case_sensitive' => false],
        ]);
        $q2->options()->create(['teks' => 'DNS', 'is_correct' => true]);

        $session = QuizSession::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi Serempak Baru',
            'mode' => 'serempak',
            'status' => 'lobby',
            'dibuka_at' => now(),
            'display_token' => Str::random(48),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.session.join', $session));

        $engine = app(QuizEngine::class);
        $engine->mulaiSesi($session);
        $session->refresh();

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('peserta.session.answer', $session), [
                'question_id' => $q1->id,
                'option_ids' => [$optSmtp->id, $optImap->id],
            ])
            ->assertOk();

        $engine->tutupSoal($session->fresh(), 1);
        $engine->lanjutSoal($session->fresh(), 1);
        $session->refresh();

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('peserta.session.answer', $session), [
                'question_id' => $q2->id,
                'jawaban_teks' => 'dns',
            ])
            ->assertOk();

        $engine->tutupSoal($session->fresh(), 2);

        $attempt = Attempt::where('quiz_session_id', $session->id)->where('user_id', $peserta->id)->firstOrFail();
        $this->assertSame(2, $attempt->jumlah_benar);
        $this->assertGreaterThan(1000, $attempt->skor_total);
    }

    private function mulaiAttempt(User $peserta, Organization $org, QuizSession $session): Attempt
    {
        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.session.join', $session));

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.attempt.start', $session));

        return Attempt::where('quiz_session_id', $session->id)->where('user_id', $peserta->id)->firstOrFail();
    }

    private function buatTenant(string $slug, string $usernamePeserta): array
    {
        $org = Organization::create([
            'name' => 'Kampus '.ucfirst($slug),
            'slug' => 'kampus-'.$slug.'-'.Str::lower(Str::random(4)),
            'type' => 'education',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => 'Admin '.ucfirst($slug),
            'username' => 'adm_'.$slug.'_'.Str::lower(Str::random(4)),
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => 'tok-adm-'.$slug,
        ]);

        $peserta = User::create([
            'nama' => 'Mahasiswa '.ucfirst($slug),
            'username' => $usernamePeserta,
            'password' => Hash::make('password123'),
            'role' => 'peserta',
            'current_session_token' => 'tok-'.$usernamePeserta,
        ]);

        $org->users()->attach($admin->id, ['role' => 'owner', 'status' => 'active']);
        $org->users()->attach($peserta->id, ['role' => 'participant', 'status' => 'active']);

        $kelas = Kelas::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'nama' => 'Kelas '.strtoupper($slug),
            'pin' => strtoupper(substr($slug.'987654', 0, 6)),
            'created_by' => $admin->id,
        ]);
        $kelas->peserta()->attach($peserta->id);

        return [$org, $admin, $peserta, $kelas];
    }
}
