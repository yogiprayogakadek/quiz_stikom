<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Kelas;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizGrade;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuizQuestionCompositionAndRegradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_kuota_terpisah_pg_dan_essay_serta_pengelompokan_tipe_soal_di_akhir(): void
    {
        [$org, $admin, $peserta, $kelas] = $this->buatTenant('alpha', 'mhs_komposisi');

        $quiz = Quiz::create([
            'organization_id' => $org->id,
            'kelas_id' => $kelas->id,
            'judul' => 'UAS Campuran Terukur',
            'jumlah_soal_pg' => 2,
            'jumlah_soal_essay' => 1,
            'acak_soal' => true,
            'kelompokkan_tipe_soal' => true,
            'acak_opsi' => true,
            'created_by' => $admin->id,
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $pg = Question::create([
                'organization_id' => $org->id,
                'quiz_id' => $quiz->id,
                'teks' => "Soal PG #{$i}",
                'tipe' => 'pilihan_ganda',
                'durasi_detik' => 30,
                'bobot' => 10,
            ]);
            $pg->options()->createMany([
                ['teks' => 'Benar', 'is_correct' => true],
                ['teks' => 'Salah', 'is_correct' => false],
            ]);
        }

        for ($j = 1; $j <= 3; $j++) {
            Question::create([
                'organization_id' => $org->id,
                'quiz_id' => $quiz->id,
                'teks' => "Soal Essay #{$j}",
                'tipe' => 'essay',
                'durasi_detik' => 120,
                'bobot' => 20,
            ]);
        }

        $this->assertSame(3, $quiz->soalTerpilih());

        $session = QuizSession::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi UAS Campuran',
            'mode' => 'acak',
            'status' => 'berjalan',
            'dibuka_at' => now(),
            'display_token' => Str::random(48),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.session.join', $session));

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.attempt.start', $session));

        $attempt = Attempt::where('quiz_session_id', $session->id)->where('user_id', $peserta->id)->firstOrFail();
        $aqs = $attempt->attemptQuestions()->with('question')->orderBy('urutan')->get();

        $this->assertCount(3, $aqs);
        $this->assertSame('pilihan_ganda', $aqs[0]->question->tipe);
        $this->assertSame('pilihan_ganda', $aqs[1]->question->tipe);
        $this->assertSame('essay', $aqs[2]->question->tipe);
    }

    public function test_nonaktifkan_acak_soal_mempertahankan_urutan_tetap(): void
    {
        [$org, $admin, $peserta, $kelas] = $this->buatTenant('beta', 'mhs_urut');

        $quiz = Quiz::create([
            'organization_id' => $org->id,
            'kelas_id' => $kelas->id,
            'judul' => 'Kuis Berurutan',
            'acak_soal' => false,
            'acak_opsi' => false,
            'created_by' => $admin->id,
        ]);

        $qKetiga = $this->buatPg($quiz, 'Soal Urutan Tiga', 3);
        $qPertama = $this->buatPg($quiz, 'Soal Urutan Satu', 1);
        $qKedua = $this->buatPg($quiz, 'Soal Urutan Dua', 2);

        $session = QuizSession::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi Urutan Tetap',
            'mode' => 'acak',
            'status' => 'berjalan',
            'dibuka_at' => now(),
            'display_token' => Str::random(48),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.session.join', $session));

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.attempt.start', $session));

        $attempt = Attempt::where('quiz_session_id', $session->id)->where('user_id', $peserta->id)->firstOrFail();
        $ids = $attempt->attemptQuestions()->orderBy('urutan')->pluck('question_id')->all();

        $this->assertSame([$qPertama->id, $qKedua->id, $qKetiga->id], $ids);
    }

    public function test_ralat_kunci_jawaban_dan_anulir_bonus_menghitung_ulang_nilai_serta_mencatat_audit(): void
    {
        [$org, $admin, $peserta, $kelas] = $this->buatTenant('gamma', 'mhs_ralat');

        $quiz = Quiz::create([
            'organization_id' => $org->id,
            'kelas_id' => $kelas->id,
            'judul' => 'UTS Ralat Kunci',
            'acak_soal' => false,
            'acak_opsi' => false,
            'created_by' => $admin->id,
        ]);

        $q1 = Question::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'teks' => 'Soal Salah Kunci Awal',
            'tipe' => 'pilihan_ganda',
            'durasi_detik' => 30,
            'bobot' => 50,
        ]);
        $opsiA = $q1->options()->create(['teks' => 'Pilihan A (Awalnya Kunci)', 'is_correct' => true]);
        $opsiB = $q1->options()->create(['teks' => 'Pilihan B (Seharusnya Benar)', 'is_correct' => false]);

        $q2 = Question::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'teks' => 'Soal Ambigu Akan Dianulir',
            'tipe' => 'pilihan_ganda',
            'durasi_detik' => 30,
            'bobot' => 50,
        ]);
        $q2->options()->create(['teks' => 'Opsi X', 'is_correct' => true]);
        $q2OpsiSalah = $q2->options()->create(['teks' => 'Opsi Y', 'is_correct' => false]);

        $session = QuizSession::create([
            'organization_id' => $org->id,
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi UTS Ralat',
            'mode' => 'acak',
            'status' => 'berjalan',
            'dibuka_at' => now(),
            'display_token' => Str::random(48),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.session.join', $session));

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.attempt.start', $session));

        $attempt = Attempt::where('quiz_session_id', $session->id)->where('user_id', $peserta->id)->firstOrFail();

        // Peserta memilih B pada soal 1 (awalnya dianggap salah) dan tidak menjawab soal 2 (kosong)
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $q1->id,
            'option_id' => $opsiB->id,
            'waktu_jawab_ms' => 3000,
            'skor' => 0,
            'answered_at' => now(),
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => $peserta->current_session_token, 'organization_id' => $org->id])
            ->post(route('peserta.attempt.finish', $attempt));

        $attempt->refresh();
        $this->assertSame(0, $attempt->skor_total);

        QuizGrade::create([
            'organization_id' => $org->id,
            'sesi_awal_id' => $session->id,
            'user_id' => $peserta->id,
            'nilai_awal' => 0,
            'nilai_remidi' => 40,
            'nilai_akhir' => 40,
            'sumber' => 'remidi',
            'ditetapkan_oleh' => $admin->id,
            'ditetapkan_at' => now(),
        ]);

        // 1) Admin meralat kunci Soal 1 dari A ke B
        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $org->id])
            ->post(route('admin.reports.session.regrade-question', [$session, $q1]), [
                'mode_ralat' => 'ubah_kunci',
                'correct_option_id' => $opsiB->id,
                'alasan' => 'Kunci awal tertukar saat penyusunan soal.',
            ])
            ->assertRedirect();

        $attempt->refresh();
        $this->assertSame(50, $attempt->skor_total);
        $this->assertFalse($opsiA->fresh()->is_correct);
        $this->assertTrue($opsiB->fresh()->is_correct);
        $this->assertSame(50, QuizGrade::where('sesi_awal_id', $session->id)->where('user_id', $peserta->id)->value('nilai_akhir'));

        // 2) Admin menganulir Soal 2 sebagai bonus penuh untuk semua peserta (termasuk yang tidak menjawab)
        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $org->id])
            ->post(route('admin.reports.session.regrade-question', [$session, $q2]), [
                'mode_ralat' => 'anulir_bonus',
                'alasan' => 'Terdapat salah ketik angka pada soal nomor dua.',
            ])
            ->assertRedirect();

        $attempt->refresh();
        $this->assertTrue($q2->fresh()->is_bonus);
        $this->assertSame(100, $attempt->skor_total);
        $this->assertSame(100, QuizGrade::where('sesi_awal_id', $session->id)->where('user_id', $peserta->id)->value('nilai_akhir'));

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'question_key_regraded',
            'subject_type' => 'question',
            'subject_id' => $q1->id,
        ]);

        unset($q2OpsiSalah);
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

    private function buatPg(Quiz $quiz, string $teks, int $urutanTetap): Question
    {
        $q = Question::create([
            'organization_id' => $quiz->organization_id,
            'quiz_id' => $quiz->id,
            'teks' => $teks,
            'tipe' => 'pilihan_ganda',
            'durasi_detik' => 30,
            'bobot' => 10,
            'urutan_tetap' => $urutanTetap,
        ]);
        $q->options()->createMany([
            ['teks' => 'A', 'is_correct' => true],
            ['teks' => 'B', 'is_correct' => false],
        ]);

        return $q;
    }
}
