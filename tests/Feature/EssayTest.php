<?php

namespace Tests\Feature;

use App\Events\EssayDinilai;
use App\Jobs\AutoGradeEssayJob;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\AuditLog;
use App\Models\Kelas;
use App\Models\Option;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use App\Services\AiQuestionService;
use App\Services\EssayGrader;
use App\Services\QuizEngine;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EssayTest extends TestCase
{
    use RefreshDatabase;

    public function test_skor_acak_memakai_bobot_dan_essay_dinilai_manual(): void
    {
        [$admin, $peserta, $session, $attempt, $pg, $essay, $benar] = $this->buatAcakBerjalan();

        app(QuizEngine::class)->selesaikanAcak($attempt);

        $attempt->refresh();
        $this->assertSame('selesai', $attempt->status);
        $this->assertSame(1, $attempt->jumlah_benar);
        $this->assertSame(2, $attempt->poin_total);
        $this->assertSame(12, $attempt->poin_maksimal);
        $this->assertSame(17, $attempt->skor_total);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$essay->id => 8],
            ])
            ->assertRedirect();

        $attempt->refresh();
        $this->assertSame(10, $attempt->poin_total);
        $this->assertSame(12, $attempt->poin_maksimal);
        $this->assertSame(83, $attempt->skor_total);

        $jawabanEssay = Answer::where('attempt_id', $attempt->id)
            ->where('question_id', $essay->id)
            ->first();

        $this->assertNotNull($jawabanEssay->dinilai_at);
        $this->assertSame(8, $jawabanEssay->skor);
        $this->assertSame($admin->id, $jawabanEssay->dinilai_oleh);
        $audit = AuditLog::where('action', 'essay_score_changed')->where('subject_id', $jawabanEssay->id)->firstOrFail();
        $this->assertNull($audit->metadata['from']);
        $this->assertSame(8, $audit->metadata['to']);
        $this->assertSame($admin->id, $audit->actor_id);
    }

    public function test_hasil_menyembunyikan_nilai_sampai_essay_terisi_selesai_dinilai(): void
    {
        [$admin, $peserta, $session, $attempt, , $essay] = $this->buatAcakBerjalan();
        $session->update(['pembahasan_mode' => 'terbuka']);
        app(QuizEngine::class)->selesaikanAcak($attempt);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertSee('data-score-pending', false)
            ->assertSee('data-essay-score-pending', false)
            ->assertSee('Menunggu penilaian essay')
            ->assertDontSee('data-final-score', false);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$essay->id => 8],
            ])
            ->assertRedirect();

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertSee('data-final-score', false)
            ->assertSee('8 / 10')
            ->assertDontSee('data-score-pending', false);
    }

    public function test_hasil_peserta_tidak_memuat_saran_ai(): void
    {
        [, $peserta, $session, $attempt, , $essay] = $this->buatAcakBerjalan();
        $session->update(['pembahasan_mode' => 'terbuka']);
        app(QuizEngine::class)->selesaikanAcak($attempt);

        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();
        $answer->update([
            'ai_suggested_score' => 8,
            'ai_confidence' => 91,
            'ai_feedback' => 'Umpan balik rahasia dari AI.',
            'ai_model' => 'test-model',
            'ai_reviewed_at' => now(),
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertSee('setelah penilaian selesai')
            ->assertDontSee('admin atau AI')
            ->assertDontSee('Saran AI')
            ->assertDontSee('Umpan balik rahasia dari AI.');
    }

    public function test_essay_kosong_tidak_dianggap_menunggu_penilaian(): void
    {
        [, $peserta, $session, $attempt] = $this->buatAcakBerjalan(false);
        $session->update(['pembahasan_mode' => 'terbuka']);
        app(QuizEngine::class)->selesaikanAcak($attempt);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertSee('data-final-score', false)
            ->assertSee('Tidak dijawab')
            ->assertDontSee('data-score-pending', false)
            ->assertDontSee('data-essay-score-pending', false);
    }

    public function test_peserta_bisa_menyimpan_dan_menghapus_jawaban_essay(): void
    {
        [$admin, $peserta, $session, $attempt, $pg, $essay] = $this->buatAcakBerjalan(false);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'jawaban_teks' => 'Jawaban essay baru.',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'jawaban_teks' => 'Jawaban essay baru.',
            'option_id' => null,
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'jawaban_teks' => '   ',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
        ]);
    }

    public function test_peserta_menyimpan_jawaban_essay_per_sub_soal_dan_menggabungkannya(): void
    {
        [$admin, $peserta, $session, $attempt, $pg, $essay] = $this->buatAcakBerjalan(false);

        $essay->update([
            'sub_soal_json' => [
                ['label' => 'A', 'teks' => 'Jelaskan topologi jaringan.', 'petunjuk' => []],
                ['label' => 'B', 'teks' => 'Pilih media transmisi.', 'petunjuk' => ['Antargedung', 'Dalam lab']],
            ],
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'part_key' => 'A',
                'jawaban_teks' => 'Topologi star memakai switch.',
            ])
            ->assertOk()
            ->assertJsonPath('jawaban_parts.A', 'Topologi star memakai switch.')
            ->assertJsonPath('jawaban_teks', "A:\nTopologi star memakai switch.");

        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();
        $this->assertSame(['A' => 'Topologi star memakai switch.'], $answer->jawaban_parts_json);
        $this->assertSame("A:\nTopologi star memakai switch.", $answer->jawaban_teks);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'part_key' => 'B',
                'jawaban_teks' => 'Fiber optik untuk antargedung.',
            ])
            ->assertOk()
            ->assertJsonPath('jawaban_parts.B', 'Fiber optik untuk antargedung.');

        $answer->refresh();
        $this->assertSame([
            'A' => 'Topologi star memakai switch.',
            'B' => 'Fiber optik untuk antargedung.',
        ], $answer->jawaban_parts_json);
        $this->assertSame(
            "A:\nTopologi star memakai switch.\n\nB:\nFiber optik untuk antargedung.",
            $answer->jawaban_teks
        );

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'part_key' => 'Z',
                'jawaban_teks' => 'Bagian tidak dikenal.',
            ])
            ->assertStatus(422);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'part_key' => 'B',
                'jawaban_teks' => '   ',
            ])
            ->assertOk()
            ->assertJsonPath('jawaban_parts.B', null);

        $answer->refresh();
        $this->assertSame(['A' => 'Topologi star memakai switch.'], $answer->jawaban_parts_json);
        $this->assertSame("A:\nTopologi star memakai switch.", $answer->jawaban_teks);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'part_key' => 'A',
                'jawaban_teks' => '',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
        ]);
    }

    public function test_peserta_hanya_menyimpan_jawaban_pada_bagian_terakhir(): void
    {
        [, $peserta, , $attempt, , $essay] = $this->buatAcakBerjalan(false);

        $essay->update([
            'sub_soal_json' => [
                ['key' => 'a', 'parent_key' => null, 'label' => 'A', 'teks' => 'Jelaskan kebutuhan.', 'petunjuk' => []],
                ['key' => 'c', 'parent_key' => null, 'label' => 'C', 'teks' => 'Rancang solusi.', 'petunjuk' => []],
                ['key' => 'c1', 'parent_key' => 'c', 'label' => '1', 'teks' => 'Pilih topologi.', 'petunjuk' => []],
                ['key' => 'c2', 'parent_key' => 'c', 'label' => '2', 'teks' => 'Pilih media.', 'petunjuk' => []],
                ['key' => 'c21', 'parent_key' => 'c2', 'label' => '1', 'teks' => 'Jelaskan alasannya.', 'petunjuk' => []],
            ],
        ]);

        $this->assertStringContainsString('C.2.1. Jelaskan alasannya.', $essay->fresh()->teksUntukPenilaian());

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.attempt.show', $attempt))
            ->assertOk()
            ->assertSee('Jelaskan alasannya.')
            ->assertSee('bagian.is_leaf', false)
            ->assertSee('bagianJawaban(current)', false);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'part_key' => 'c',
                'jawaban_teks' => 'Induk tidak boleh dijawab.',
            ])
            ->assertStatus(422);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'part_key' => 'c1',
                'jawaban_teks' => 'Topologi star.',
            ])
            ->assertOk()
            ->assertJsonPath('jawaban_teks', "C.1:\nTopologi star.");

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->postJson(route('peserta.attempt.answer', $attempt), [
                'question_id' => $essay->id,
                'part_key' => 'c21',
                'jawaban_teks' => 'Fiber optik lebih stabil.',
            ])
            ->assertOk()
            ->assertJsonPath('jawaban_teks', "C.1:\nTopologi star.\n\nC.2.1:\nFiber optik lebih stabil.");

        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();
        $this->assertSame([
            'c1' => 'Topologi star.',
            'c21' => 'Fiber optik lebih stabil.',
        ], $answer->jawaban_parts_json);
    }

    public function test_halaman_mengerjakan_menampilkan_sub_soal_dan_jawaban_tersimpan(): void
    {
        [$admin, $peserta, $session, $attempt, $pg, $essay] = $this->buatAcakBerjalan(false);

        $essay->update([
            'petunjuk_json' => ['Topologi jaringan', 'Media transmisi'],
            'sub_soal_json' => [
                ['label' => 'A', 'teks' => 'Jelaskan topologi jaringan.', 'petunjuk' => []],
                ['label' => 'B', 'teks' => 'Pilih media transmisi.', 'petunjuk' => ['Antargedung', 'Dalam lab']],
            ],
        ]);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'jawaban_teks' => "A:\nTopologi star memakai switch.",
            'jawaban_parts_json' => ['A' => 'Topologi star memakai switch.'],
            'waktu_jawab_ms' => 2000,
            'skor' => 0,
            'answered_at' => now(),
        ]);

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.attempt.show', $attempt))
            ->assertOk()
            ->assertSee('Topologi jaringan')
            ->assertSee('Jelaskan topologi jaringan.')
            ->assertSee('Topologi star memakai switch.')
            ->assertSee('Pilih media transmisi.')
            ->assertSee('x-bind:disabled="mengumpulkan || berhenti"', false)
            ->assertSee("tampilkanProgress('Menyinkronkan jawaban...', 0)", false)
            ->assertSee('requestSubmit()', false)
            ->assertSee('data-progress-message="Mengunci jawaban dan menghitung hasil..."', false)
            ->assertSee('data-progress-delay="0"', false);
    }

    public function test_laporan_admin_menampilkan_jawaban_essay_per_sub_soal(): void
    {
        [$admin, $peserta, $session, $attempt, $pg, $essay] = $this->buatAcakBerjalan(false);

        $essay->update([
            'sub_soal_json' => [
                ['key' => 'a', 'parent_key' => null, 'label' => 'A', 'teks' => 'Jelaskan topologi jaringan.', 'petunjuk' => []],
                ['key' => 'c', 'parent_key' => null, 'label' => 'C', 'teks' => 'Pilih media transmisi.', 'petunjuk' => ['Antargedung', 'Dalam lab']],
                ['key' => 'c1', 'parent_key' => 'c', 'label' => '1', 'teks' => 'Berikan alasan pemilihan media.', 'petunjuk' => []],
            ],
        ]);
        $session->update(['pembahasan_mode' => 'terbuka']);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'jawaban_teks' => "A:\nTopologi star.\n\nC.1:\nFiber optik.",
            'jawaban_parts_json' => [
                'a' => 'Topologi star.',
                'c1' => 'Fiber optik.',
            ],
            'waktu_jawab_ms' => 2000,
            'skor' => 0,
            'answered_at' => now(),
        ]);

        app(QuizEngine::class)->selesaikanAcak($attempt);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->get(route('admin.reports.attempt', $attempt))
            ->assertOk()
            ->assertSee('Jelaskan topologi jaringan.')
            ->assertSee('Topologi star.')
            ->assertSee('Pilih media transmisi.')
            ->assertSee('Berikan alasan pemilihan media.')
            ->assertSee('Fiber optik.');

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.attempt.result', $attempt))
            ->assertOk()
            ->assertSee('Pilih media transmisi.')
            ->assertSee('Berikan alasan pemilihan media.')
            ->assertSee('Fiber optik.');
    }

    public function test_mode_serempak_menolak_soal_essay(): void
    {
        $admin = User::create([
            'nama' => 'Admin',
            'username' => 'admin-serempak-essay',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Essay',
            'created_by' => $admin->id,
        ]);

        $essay = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Jelaskan sesuatu.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Serempak Essay',
            'mode' => 'serempak',
            'status' => 'lobby',
            'display_token' => 'token-serempak-essay',
            'created_by' => $admin->id,
        ]);

        try {
            app(QuizEngine::class)->mulaiSesi($session);
            $this->fail('Mode Serempak seharusnya menolak soal essay.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sesi', $e->errors());
        }

        $this->assertSame('lobby', $session->fresh()->status);
    }

    public function test_kuis_menyimpan_flag_auto_grade_essay(): void
    {
        $admin = User::create([
            'nama' => 'Admin',
            'username' => 'admin-auto-flag',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-auto-flag',
        ]);

        $kelas = Kelas::create([
            'nama' => 'Kelas Auto',
            'pin' => 'AUTO01',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-auto-flag'])
            ->post(route('admin.quizzes.store'), [
                'kelas_id' => $kelas->id,
                'judul' => 'Kuis Auto',
            ])
            ->assertRedirect();

        $quiz = Quiz::where('judul', 'Kuis Auto')->firstOrFail();
        $this->assertFalse($quiz->auto_grade_essay);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-auto-flag'])
            ->put(route('admin.quizzes.update', $quiz), [
                'kelas_id' => $kelas->id,
                'judul' => 'Kuis Auto',
                'auto_grade_essay' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($quiz->fresh()->auto_grade_essay);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-auto-flag'])
            ->get(route('admin.quizzes.edit', $quiz))
            ->assertSee('Nilai essay otomatis dengan AI');
    }

    public function test_force_organisasi_memakai_pengaturan_ai_organisasi(): void
    {
        $organization = Organization::query()->firstOrFail();

        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'ai_base_url' => 'https://org.example.test/v1',
            'ai_api_key' => 'org-key',
            'ai_default_model' => 'org-model',
        ]);

        config([
            'services.ai.base_url' => 'https://platform.example.test/v1',
            'services.ai.api_key' => 'platform-key',
            'services.ai.default_model' => 'platform-model',
        ]);

        Http::fake([
            'https://org.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 7,
                            'confidence' => 80,
                            'feedback' => 'Cukup.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        $context = app(OrganizationContext::class);
        $context->force($organization);

        try {
            $result = app(AiQuestionService::class)
                ->evaluateEssay('Pertanyaan', null, [], 'Jawaban', 10);
        } finally {
            $context->force(null);
        }

        $this->assertSame('org-model', $result['model']);
        Http::assertSent(fn ($request) => $request->url() === 'https://org.example.test/v1/chat/completions');
    }

    public function test_essay_grader_menyimpan_saran_dan_nilai_final(): void
    {
        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 8,
                            'confidence' => 90,
                            'feedback' => 'Jawaban tepat.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        config([
            'services.ai.base_url' => 'https://ai.example.test/v1',
            'services.ai.api_key' => 'test-key',
            'services.ai.default_model' => 'test-model',
        ]);

        [$admin, , , $attempt, , $essay] = $this->buatAcakBerjalan();
        app(QuizEngine::class)->selesaikanAcak($attempt);

        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();
        $grader = app(EssayGrader::class);

        $suggestion = $grader->suggest($answer);
        $this->assertTrue($grader->applyFinal($answer->fresh(), $suggestion['score'], $admin->id));

        $answer->refresh();
        $this->assertSame(8, $answer->skor);
        $this->assertNotNull($answer->dinilai_at);
        $this->assertSame($admin->id, $answer->dinilai_oleh);
    }

    public function test_kuis_auto_grade_mengantre_job_saat_attempt_selesai(): void
    {
        Queue::fake();
        [, , , $attempt] = $this->buatAcakBerjalan(true, true);

        app(QuizEngine::class)->selesaikanAcak($attempt);

        Queue::assertPushed(AutoGradeEssayJob::class, fn ($job) => $job->attemptId === $attempt->id);
    }

    public function test_kuis_manual_tidak_mengantre_job_auto_grade(): void
    {
        Queue::fake();
        [, , , $attempt] = $this->buatAcakBerjalan(true, false);

        app(QuizEngine::class)->selesaikanAcak($attempt);

        Queue::assertNotPushed(AutoGradeEssayJob::class);
    }

    public function test_kuis_auto_grade_mengantre_job_saat_sesi_ditutup(): void
    {
        Queue::fake();
        [, , $session, $attempt] = $this->buatAcakBerjalan(true, true);

        app(QuizEngine::class)->tutupSesi($session);

        Queue::assertPushed(AutoGradeEssayJob::class, fn ($job) => $job->attemptId === $attempt->id);
    }

    public function test_job_auto_grade_menyimpan_nilai_final_dan_audit(): void
    {
        config([
            'services.ai.base_url' => 'https://ai.example.test/v1',
            'services.ai.api_key' => 'test-key',
            'services.ai.default_model' => 'test-model',
        ]);
        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 8,
                            'confidence' => 90,
                            'feedback' => 'Jawaban tepat.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        [, , , $attempt, , $essay] = $this->buatAcakBerjalan(true, true);
        app(QuizEngine::class)->selesaikanAcak($attempt);

        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();
        $answer->refresh();

        $this->assertSame(8, $answer->skor);
        $this->assertNotNull($answer->dinilai_at);
        $this->assertNull($answer->dinilai_oleh);
        $this->assertSame(8, $answer->ai_suggested_score);

        $final = AuditLog::where('action', 'essay_score_changed')
            ->where('subject_id', $answer->id)
            ->firstOrFail();
        $this->assertNull($final->actor_id);
        $this->assertTrue($final->metadata['auto']);
        $this->assertSame(8, $final->metadata['to']);

        $this->assertDatabaseHas('ai_usage_events', [
            'organization_id' => $attempt->organization_id,
            'kind' => 'essay',
            'requests' => 1,
        ]);
    }

    public function test_job_auto_grade_mengirim_event_setelah_skor_total_dihitung(): void
    {
        config([
            'services.ai.base_url' => 'https://ai.example.test/v1',
            'services.ai.api_key' => 'test-key',
            'services.ai.default_model' => 'test-model',
        ]);
        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 8,
                            'confidence' => 90,
                            'feedback' => 'Jawaban tepat.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        [, , , $attempt] = $this->buatAcakBerjalan(true, true);

        $skorSaatEvent = null;
        Event::listen(EssayDinilai::class, function () use ($attempt, &$skorSaatEvent) {
            $skorSaatEvent = Attempt::find($attempt->id)?->skor_total;
        });

        app(QuizEngine::class)->selesaikanAcak($attempt);

        $this->assertSame($attempt->fresh()->skor_total, $skorSaatEvent);
        $this->assertSame(83, $skorSaatEvent);
    }

    public function test_job_auto_grade_tidak_menimpa_nilai_admin(): void
    {
        Queue::fake();
        [$admin, , , $attempt, , $essay] = $this->buatAcakBerjalan(true, true);
        app(QuizEngine::class)->selesaikanAcak($attempt);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$essay->id => 9],
            ])
            ->assertRedirect();

        (new AutoGradeEssayJob($attempt->id))->handle(app(QuizEngine::class));

        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();
        $this->assertSame(9, $answer->skor);
        $this->assertSame(1, AuditLog::where('action', 'essay_score_changed')->where('subject_id', $answer->id)->count());
    }

    public function test_job_auto_grade_melewati_jawaban_saat_kuota_habis(): void
    {
        config(['platform.ai_monthly_requests' => 0]);
        Http::fake();

        [, , , $attempt, , $essay] = $this->buatAcakBerjalan(true, true);
        app(QuizEngine::class)->selesaikanAcak($attempt);

        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();
        $this->assertNull($answer->dinilai_at);
        Http::assertNothingSent();
    }

    public function test_job_auto_grade_tetap_selesai_saat_ai_error(): void
    {
        config([
            'services.ai.base_url' => 'https://ai.example.test/v1',
            'services.ai.api_key' => 'test-key',
            'services.ai.default_model' => 'test-model',
        ]);
        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response('', 500),
        ]);

        [, , , $attempt, , $essay] = $this->buatAcakBerjalan(true, true);
        app(QuizEngine::class)->selesaikanAcak($attempt);

        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();
        $this->assertSame('selesai', $attempt->fresh()->status);
        $this->assertNull($answer->dinilai_at);
    }

    public function test_job_auto_grade_berhenti_saat_429_tanpa_menilai(): void
    {
        config([
            'services.ai.base_url' => 'https://ai.example.test/v1',
            'services.ai.api_key' => 'test-key',
            'services.ai.default_model' => 'test-model',
        ]);
        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'error' => ['message' => 'rate limit reached'],
            ], 429, ['Retry-After' => '60']),
        ]);

        [, , , $attempt, , $essay] = $this->buatAcakBerjalan(true, true);
        app(QuizEngine::class)->selesaikanAcak($attempt);

        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();
        $this->assertNull($answer->dinilai_at);
        Http::assertSentCount(1);
    }

    public function test_job_auto_grade_tanpa_jawaban_essay_tidak_memanggil_ai(): void
    {
        Http::fake();

        [, , , $attempt] = $this->buatAcakBerjalan(false, true);
        app(QuizEngine::class)->selesaikanAcak($attempt);

        Http::assertNothingSent();
    }

    public function test_job_auto_grade_tidak_menimpa_saat_dijalankan_dua_kali(): void
    {
        config([
            'services.ai.base_url' => 'https://ai.example.test/v1',
            'services.ai.api_key' => 'test-key',
            'services.ai.default_model' => 'test-model',
        ]);
        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 8,
                            'confidence' => 90,
                            'feedback' => 'Jawaban tepat.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        [, , , $attempt, , $essay] = $this->buatAcakBerjalan(true, true);
        app(QuizEngine::class)->selesaikanAcak($attempt);
        (new AutoGradeEssayJob($attempt->id))->handle(app(QuizEngine::class));

        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();
        $this->assertSame(8, $answer->skor);
        $this->assertSame(1, AuditLog::where('action', 'essay_score_changed')->where('subject_id', $answer->id)->count());
    }

    public function test_job_auto_grade_memakai_provider_organisasi(): void
    {
        $organization = Organization::query()->firstOrFail();
        OrganizationSetting::create([
            'organization_id' => $organization->id,
            'ai_base_url' => 'https://org.example.test/v1',
            'ai_api_key' => 'org-key',
            'ai_default_model' => 'org-model',
        ]);

        config([
            'services.ai.base_url' => 'https://platform.example.test/v1',
            'services.ai.api_key' => 'platform-key',
            'services.ai.default_model' => 'platform-model',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://org.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 8,
                            'confidence' => 90,
                            'feedback' => 'Jawaban tepat.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        [, , , $attempt] = $this->buatAcakBerjalan(true, true);
        app(QuizEngine::class)->selesaikanAcak($attempt);

        Http::assertSent(fn ($request) => $request->url() === 'https://org.example.test/v1/chat/completions');
        $this->assertDatabaseHas('ai_usage_events', [
            'organization_id' => $attempt->organization_id,
            'kind' => 'essay',
            'requests' => 1,
        ]);
    }

    public function test_admin_dapat_meminta_saran_ai_tanpa_menetapkan_nilai_final(): void
    {
        config([
            'services.ai.base_url' => 'https://ai.example.test/v1',
            'services.ai.api_key' => 'test-key',
            'services.ai.default_model' => 'test-model',
        ]);
        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 8,
                            'confidence' => 91,
                            'feedback' => 'Jawaban menyebut TLS dan perbedaan enkripsi.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        [$admin, , , $attempt, , $essay] = $this->buatAcakBerjalan();
        app(QuizEngine::class)->selesaikanAcak($attempt);
        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.answers.ai-suggestion', $answer))
            ->assertRedirect();

        $answer->refresh();
        $this->assertSame(8, $answer->ai_suggested_score);
        $this->assertSame(91, $answer->ai_confidence);
        $this->assertSame(0, $answer->skor);
        $this->assertNull($answer->dinilai_at);
        $audit = AuditLog::where('action', 'essay_ai_suggested')->where('subject_id', $answer->id)->firstOrFail();
        $this->assertSame('ai.example.test', $audit->metadata['provider']);
        $this->assertSame('test-model', $audit->metadata['model']);
        $this->assertDatabaseHas('ai_usage_events', [
            'organization_id' => $attempt->organization_id,
            'kind' => 'essay',
            'requests' => 1,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->get(route('admin.reports.attempt', $attempt))
            ->assertOk()
            ->assertSee('Riwayat penilaian (1)')
            ->assertSee('ai.example.test');
    }

    public function test_saran_ai_mengembalikan_json_untuk_permintaan_menyeluruh(): void
    {
        config([
            'services.ai.base_url' => 'https://ai.example.test/v1',
            'services.ai.api_key' => 'test-key',
            'services.ai.default_model' => 'test-model',
        ]);
        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 8,
                            'confidence' => 91,
                            'feedback' => 'Jawaban menyebut TLS dan perbedaan enkripsi.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        [$admin, , , $attempt, , $essay] = $this->buatAcakBerjalan();
        app(QuizEngine::class)->selesaikanAcak($attempt);
        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->postJson(route('admin.answers.ai-suggestion', $answer))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'skor' => 8,
                'keyakinan' => 91,
                'umpan_balik' => 'Jawaban menyebut TLS dan perbedaan enkripsi.',
            ]);

        $answer->refresh();
        $this->assertSame(8, $answer->ai_suggested_score);
        $this->assertSame(0, $answer->skor);
        $this->assertNull($answer->dinilai_at);
    }

    public function test_saran_ai_json_menandai_429_sebagai_gangguan_sementara(): void
    {
        config([
            'services.ai.base_url' => 'https://ai.example.test/v1',
            'services.ai.api_key' => 'test-key',
            'services.ai.default_model' => 'test-model',
        ]);
        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Batas kuota tercapai.'],
            ], 429, ['Retry-After' => '30']),
        ]);

        [$admin, , , $attempt, , $essay] = $this->buatAcakBerjalan();
        app(QuizEngine::class)->selesaikanAcak($attempt);
        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->postJson(route('admin.answers.ai-suggestion', $answer))
            ->assertOk()
            ->assertJson([
                'ok' => false,
                'transient' => true,
            ]);

        $this->assertNull($answer->fresh()->ai_reviewed_at);
    }

    /**
     * @return array{0: User, 1: User, 2: QuizSession, 3: Attempt, 4: Question, 5: Question, 6: Option}
     */
    public function test_halaman_mengerjakan_soal_tanpa_sidebar_peserta(): void
    {
        [$admin, $peserta, $session, $attempt] = $this->buatAcakBerjalan();

        $this->actingAs($peserta)
            ->withSession(['auth_token' => 'token-peserta'])
            ->get(route('peserta.attempt.show', $attempt))
            ->assertOk()
            ->assertSee('Ibu kota Indonesia?')
            ->assertDontSee('Kelas Saya')
            ->assertDontSee('Portal Peserta');
    }

    public function test_riwayat_nilai_final_menampilkan_provider_model_dan_pemberi_nilai(): void
    {
        config([
            'services.ai.base_url' => 'https://ai.example.test/v1',
            'services.ai.api_key' => 'test-key',
            'services.ai.default_model' => 'test-model',
        ]);
        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'score' => 8,
                            'confidence' => 91,
                            'feedback' => 'Jawaban tepat.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        [$admin, , , $attempt, , $essay] = $this->buatAcakBerjalan();
        app(QuizEngine::class)->selesaikanAcak($attempt);
        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay->id)->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.answers.ai-suggestion', $answer));

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.attempts.nilai', $attempt), ['nilai' => [$essay->id => 8]]);

        $final = AuditLog::where('action', 'essay_score_changed')->where('subject_id', $answer->id)->firstOrFail();
        $this->assertSame('ai.example.test', $final->metadata['ai_provider']);
        $this->assertSame('test-model', $final->metadata['ai_model']);
        $this->assertSame($admin->id, $final->actor_id);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->get(route('admin.reports.attempt', $attempt))
            ->assertOk()
            ->assertSee('ai.example.test')
            ->assertSee('test-model')
            ->assertSee('Admin');
    }

    public function test_riwayat_nilai_manual_tanpa_ai_tetap_menampilkan_pemberi_nilai(): void
    {
        [$admin, , , $attempt, , $essay] = $this->buatAcakBerjalan();
        app(QuizEngine::class)->selesaikanAcak($attempt);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->post(route('admin.attempts.nilai', $attempt), ['nilai' => [$essay->id => 7]]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-admin'])
            ->get(route('admin.reports.attempt', $attempt))
            ->assertOk()
            ->assertSee('Nilai final ditetapkan menjadi 7.')
            ->assertSee('Admin')
            ->assertDontSee('Saran AI 7 poin');
    }

    public function test_ruang_penilaian_hanya_memuat_soal_essay_dengan_status_navigator(): void
    {
        [$admin, $attempt, $essay1, $essay2, $essay3, $pg] = $this->buatRuangPenilaian();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-ruang'])
            ->get(route('admin.reports.attempt', $attempt))
            ->assertOk()
            ->assertSee('data-essay-nav', false)
            ->assertSee('data-essay-soal="'.$essay1->id.'"', false)
            ->assertSee('data-essay-soal="'.$essay2->id.'"', false)
            ->assertSee('data-essay-soal="'.$essay3->id.'"', false)
            ->assertDontSee('data-essay-soal="'.$pg->id.'"', false)
            ->assertSee('data-essay-status="graded"', false)
            ->assertSee('data-essay-status="pending"', false)
            ->assertSee('data-essay-status="unanswered"', false)
            ->assertSee('data-hitung-dinilai="1"', false)
            ->assertSee('data-hitung-pending="1"', false)
            ->assertSee('data-hitung-kosong="1"', false)
            ->assertSee('data-active-essay="'.$essay2->id.'"', false)
            ->assertSee('data-nav-sebelumnya="'.$essay1->id.'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Saran AI')
            ->assertSee('0 dari 2')
            ->assertSee('Bukan nilai final')
            ->assertSee('Penilaian manual')
            ->assertSee('data-saran-tujuan', false)
            ->assertSee('<dialog', false)
            ->assertSee('x-ref="dialogSaran"', false)
            ->assertSee('cekTutupSaran', false)
            ->assertSee('Minta saran')
            ->assertSee('Membuat saran AI')
            ->assertSee('Hentikan')
            ->assertSee('Progres pembuatan saran AI');
    }

    public function test_kartu_saran_ai_menampilkan_total_poin(): void
    {
        [$admin, $attempt, $essay1, $essay2] = $this->buatRuangPenilaian();

        Answer::where('attempt_id', $attempt->id)->where('question_id', $essay1->id)->update([
            'ai_suggested_score' => 8,
            'ai_confidence' => 90,
            'ai_feedback' => 'Saran pertama.',
            'ai_model' => 'test-model',
            'ai_reviewed_at' => now(),
        ]);
        Answer::where('attempt_id', $attempt->id)->where('question_id', $essay2->id)->update([
            'ai_suggested_score' => 6,
            'ai_confidence' => 80,
            'ai_feedback' => 'Saran kedua.',
            'ai_model' => 'test-model',
            'ai_reviewed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-ruang'])
            ->get(route('admin.reports.attempt', $attempt))
            ->assertOk()
            ->assertSee('2 dari 2 essay')
            ->assertSee('Poin saran AI')
            ->assertSee('14 / 20')
            ->assertSee('Bukan nilai final');
    }

    public function test_soal_aktif_mengikuti_query_soal_dan_mengabaikan_query_asing(): void
    {
        [$admin, $attempt, $essay1, $essay2, $essay3, $pg] = $this->buatRuangPenilaian();

        $pesertaLain = User::create([
            'nama' => 'Peserta Lain',
            'username' => 'peserta-lain-ruang',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
        ]);

        $soalAttemptLain = Question::create([
            'quiz_id' => $attempt->quizSession->quiz_id,
            'teks' => 'Soal milik attempt lain.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);

        $attemptLain = Attempt::create([
            'user_id' => $pesertaLain->id,
            'quiz_session_id' => $attempt->quiz_session_id,
            'status' => 'selesai',
            'mulai_at' => now()->getTimestampMs(),
            'selesai_at' => now()->getTimestampMs(),
        ]);

        AttemptQuestion::create([
            'attempt_id' => $attemptLain->id,
            'question_id' => $soalAttemptLain->id,
            'urutan' => 1,
            'urutan_opsi_json' => [],
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-ruang'])
            ->get(route('admin.reports.attempt', ['attempt' => $attempt, 'soal' => $essay3->id]))
            ->assertOk()
            ->assertSee('data-active-essay="'.$essay3->id.'"', false)
            ->assertDontSee('data-active-essay="'.$essay2->id.'"', false);

        foreach ([$pg->id, 999999, $soalAttemptLain->id] as $soalAsing) {
            $this->actingAs($admin)
                ->withSession(['auth_token' => 'token-ruang'])
                ->get(route('admin.reports.attempt', ['attempt' => $attempt, 'soal' => $soalAsing]))
                ->assertOk()
                ->assertSee('data-active-essay="'.$essay2->id.'"', false)
                ->assertDontSee('data-active-essay="'.$essay3->id.'"', false);
        }
    }

    public function test_input_nilai_kosong_untuk_soal_belum_dinilai_dan_nol_tetap_tampil(): void
    {
        [$admin, $attempt, $essay1, $essay2, $essay3] = $this->buatRuangPenilaian();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-ruang'])
            ->get(route('admin.reports.attempt', $attempt))
            ->assertSee('name="nilai['.$essay2->id.']" type="number" min="0" max="10" value=""', false);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-ruang'])
            ->get(route('admin.reports.attempt', ['attempt' => $attempt, 'soal' => $essay1->id]))
            ->assertSee('name="nilai['.$essay1->id.']" type="number" min="0" max="10" value="0"', false);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-ruang'])
            ->get(route('admin.reports.attempt', ['attempt' => $attempt, 'soal' => $essay3->id]))
            ->assertSee('name="nilai['.$essay3->id.']" type="number" min="0" max="10" value=""', false)
            ->assertSee('disabled', false);
    }

    public function test_menyimpan_satu_soal_tidak_menyentuh_nilai_dan_audit_soal_lain(): void
    {
        [$admin, $attempt, $essay1, $essay2, $essay3] = $this->buatRuangPenilaian();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-ruang'])
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$essay2->id => 7],
                'focus_question_id' => $essay2->id,
                'after_save' => 'stay',
            ])
            ->assertRedirect(
                route('admin.reports.attempt', ['attempt' => $attempt, 'soal' => $essay2->id]).'#penilaian-essay'
            );

        $jawabanSatu = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay1->id)->firstOrFail();
        $this->assertSame(0, $jawabanSatu->skor);
        $this->assertNull($jawabanSatu->dinilai_oleh);
        $this->assertSame(0, AuditLog::where('action', 'essay_score_changed')->where('subject_id', $jawabanSatu->id)->count());

        $jawabanDua = Answer::where('attempt_id', $attempt->id)->where('question_id', $essay2->id)->firstOrFail();
        $this->assertSame(7, $jawabanDua->skor);
        $this->assertNotNull($jawabanDua->dinilai_at);
        $this->assertSame(1, AuditLog::where('action', 'essay_score_changed')->where('subject_id', $jawabanDua->id)->count());

        $this->assertDatabaseMissing('answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $essay3->id,
        ]);
    }

    public function test_simpan_dan_berikutnya_menuju_soal_berikutnya_dan_melewati_batas_akhir(): void
    {
        [$admin, $attempt, $essay1, $essay2, $essay3] = $this->buatRuangPenilaian();

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-ruang'])
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$essay1->id => 5],
                'focus_question_id' => $essay1->id,
                'after_save' => 'next',
            ])
            ->assertRedirect(
                route('admin.reports.attempt', ['attempt' => $attempt, 'soal' => $essay2->id]).'#penilaian-essay'
            );

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay3->id,
            'jawaban_teks' => 'Jawaban ketiga.',
            'waktu_jawab_ms' => 3000,
            'skor' => 0,
            'answered_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-ruang'])
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$essay3->id => 4],
                'focus_question_id' => $essay3->id,
                'after_save' => 'next',
            ])
            ->assertRedirect(
                route('admin.reports.attempt', ['attempt' => $attempt, 'soal' => $essay2->id]).'#penilaian-essay'
            );
    }

    public function test_simpan_dan_berikutnya_saat_semua_soal_terisi_dinilai_tetap_di_soal_terakhir(): void
    {
        [$admin, $attempt, , , $essay3] = $this->buatRuangPenilaian(true);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-ruang'])
            ->post(route('admin.attempts.nilai', $attempt), [
                'nilai' => [$essay3->id => null],
                'focus_question_id' => $essay3->id,
                'after_save' => 'next',
            ])
            ->assertRedirect(
                route('admin.reports.attempt', ['attempt' => $attempt, 'soal' => $essay3->id]).'#penilaian-essay'
            )
            ->assertSessionHas('sukses', 'Seluruh jawaban essay terisi sudah dinilai.');
    }

    public function test_peserta_berikutnya_hanya_dari_sesi_dan_workspace_yang_sama(): void
    {
        [$admin, $attempt, $essay1] = $this->buatRuangPenilaian(true);

        $pesertaLain = User::create([
            'nama' => 'Peserta Berikutnya',
            'username' => 'peserta-berikutnya',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
        ]);

        $attemptBerikutnya = Attempt::create([
            'user_id' => $pesertaLain->id,
            'quiz_session_id' => $attempt->quiz_session_id,
            'status' => 'selesai',
            'mulai_at' => now()->getTimestampMs(),
            'selesai_at' => now()->getTimestampMs(),
        ]);

        Answer::create([
            'attempt_id' => $attemptBerikutnya->id,
            'question_id' => $essay1->id,
            'jawaban_teks' => 'Jawaban belum dinilai.',
            'waktu_jawab_ms' => 1000,
            'skor' => 0,
            'answered_at' => now(),
        ]);

        $sesiLain = QuizSession::create([
            'quiz_id' => $attempt->quizSession->quiz_id,
            'nama' => 'Sesi Lain',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'token-sesi-lain',
            'created_by' => $admin->id,
        ]);

        $attemptSesiLain = Attempt::create([
            'user_id' => $pesertaLain->id,
            'quiz_session_id' => $sesiLain->id,
            'status' => 'selesai',
            'mulai_at' => now()->getTimestampMs(),
            'selesai_at' => now()->getTimestampMs(),
        ]);

        Answer::create([
            'attempt_id' => $attemptSesiLain->id,
            'question_id' => $essay1->id,
            'jawaban_teks' => 'Jawaban sesi lain.',
            'waktu_jawab_ms' => 1000,
            'skor' => 0,
            'answered_at' => now(),
        ]);

        $organisasiLain = Organization::create([
            'name' => 'Workspace Lain',
            'slug' => 'workspace-lain',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $quizAsing = Quiz::create([
            'judul' => 'Kuis Workspace Lain',
            'organization_id' => $organisasiLain->id,
            'created_by' => $admin->id,
        ]);

        $sesiAsing = QuizSession::create([
            'quiz_id' => $quizAsing->id,
            'organization_id' => $organisasiLain->id,
            'nama' => 'Sesi Workspace Lain',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'token-sesi-asing',
            'created_by' => $admin->id,
        ]);

        $attemptAsing = Attempt::create([
            'user_id' => $pesertaLain->id,
            'organization_id' => $organisasiLain->id,
            'quiz_session_id' => $sesiAsing->id,
            'status' => 'selesai',
            'mulai_at' => now()->getTimestampMs(),
            'selesai_at' => now()->getTimestampMs(),
        ]);

        Answer::create([
            'attempt_id' => $attemptAsing->id,
            'question_id' => $essay1->id,
            'jawaban_teks' => 'Jawaban workspace lain.',
            'waktu_jawab_ms' => 1000,
            'skor' => 0,
            'answered_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'token-ruang'])
            ->get(route('admin.reports.attempt', $attempt))
            ->assertOk()
            ->assertSee('data-peserta-berikutnya="'.$attemptBerikutnya->id.'"', false)
            ->assertDontSee('data-peserta-berikutnya="'.$attemptSesiLain->id.'"', false)
            ->assertDontSee('data-peserta-berikutnya="'.$attemptAsing->id.'"', false);
    }

    /**
     * Attempt selesai dengan tiga essay berurutan dan satu pilihan ganda.
     * Essay pertama sudah dinilai dengan nilai 0, essay kedua menunggu,
     * essay ketiga tidak dijawab.
     *
     * @return array{0: User, 1: Attempt, 2: Question, 3: Question, 4: Question, 5: Question}
     */
    private function buatRuangPenilaian(bool $semuaEssayTerisiDinilai = false): array
    {
        $admin = User::create([
            'nama' => 'Admin Ruang',
            'username' => 'admin-ruang',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-ruang',
        ]);

        $peserta = User::create([
            'nama' => 'Peserta Ruang',
            'username' => 'peserta-ruang',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
            'current_session_token' => 'token-ruang-peserta',
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Ruang Nilai',
            'created_by' => $admin->id,
        ]);

        $pg = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Soal pilihan ganda ruang nilai.',
            'tipe' => 'pilihan_ganda',
            'bobot' => 2,
            'durasi_detik' => 60,
        ]);

        $benar = Option::create([
            'question_id' => $pg->id,
            'teks' => 'Jawaban benar',
            'is_correct' => true,
        ]);

        $essay1 = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Soal essay pertama ruang nilai.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
            'kunci_essay' => 'Kunci essay pertama.',
        ]);

        $essay2 = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Soal essay kedua ruang nilai.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
            'kunci_essay' => 'Kunci essay kedua.',
        ]);

        $essay3 = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Soal essay ketiga ruang nilai.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
            'kunci_essay' => 'Kunci essay ketiga.',
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Ruang Nilai',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'token-ruang-nilai',
            'created_by' => $admin->id,
        ]);

        $attempt = Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'selesai',
            'mulai_at' => now()->getTimestampMs(),
            'selesai_at' => now()->getTimestampMs(),
            'bergabung_at' => now(),
        ]);

        foreach ([[$essay1, 1], [$pg, 2], [$essay2, 3], [$essay3, 4]] as [$soal, $urutan]) {
            AttemptQuestion::create([
                'attempt_id' => $attempt->id,
                'question_id' => $soal->id,
                'urutan' => $urutan,
                'urutan_opsi_json' => [],
            ]);
        }

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $pg->id,
            'option_id' => $benar->id,
            'waktu_jawab_ms' => 500,
            'skor' => 0,
            'answered_at' => now(),
        ]);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay1->id,
            'jawaban_teks' => 'Jawaban essay pertama.',
            'waktu_jawab_ms' => 1000,
            'skor' => 0,
            'dinilai_at' => now(),
            'answered_at' => now(),
        ]);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay2->id,
            'jawaban_teks' => 'Jawaban essay kedua.',
            'waktu_jawab_ms' => 2000,
            'skor' => $semuaEssayTerisiDinilai ? 6 : 0,
            'dinilai_at' => $semuaEssayTerisiDinilai ? now() : null,
            'answered_at' => now(),
        ]);

        return [$admin, $attempt, $essay1, $essay2, $essay3, $pg];
    }

    private function buatAcakBerjalan(bool $denganJawaban = true, bool $autoGrade = false): array
    {
        $admin = User::create([
            'nama' => 'Admin',
            'username' => 'admin-essay',
            'password' => Hash::make('rahasia'),
            'role' => 'admin',
            'current_session_token' => 'token-admin',
        ]);

        $peserta = User::create([
            'nama' => 'Peserta',
            'username' => 'peserta-essay',
            'password' => Hash::make('rahasia'),
            'role' => 'peserta',
            'current_session_token' => 'token-peserta',
        ]);

        $quiz = Quiz::create([
            'judul' => 'Kuis Essay',
            'auto_grade_essay' => $autoGrade,
            'created_by' => $admin->id,
        ]);

        $pg = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Ibu kota Indonesia?',
            'tipe' => 'pilihan_ganda',
            'bobot' => 2,
            'durasi_detik' => 60,
        ]);

        $benar = Option::create([
            'question_id' => $pg->id,
            'teks' => 'Jakarta',
            'is_correct' => true,
        ]);

        Option::create([
            'question_id' => $pg->id,
            'teks' => 'Bandung',
            'is_correct' => false,
        ]);

        $essay = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Jelaskan perbedaan HTTP dan HTTPS.',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
            'kunci_essay' => 'HTTPS memakai TLS.',
        ]);

        $session = QuizSession::create([
            'quiz_id' => $quiz->id,
            'nama' => 'Sesi Essay',
            'mode' => 'acak',
            'status' => 'berjalan',
            'display_token' => 'token-acak-essay',
            'created_by' => $admin->id,
        ]);

        $attempt = Attempt::create([
            'user_id' => $peserta->id,
            'quiz_session_id' => $session->id,
            'status' => 'berjalan',
            'mulai_at' => now()->getTimestampMs(),
            'batas_akhir_at' => now()->addHour()->getTimestampMs(),
            'bergabung_at' => now(),
        ]);

        AttemptQuestion::create([
            'attempt_id' => $attempt->id,
            'question_id' => $pg->id,
            'urutan' => 1,
            'urutan_opsi_json' => [$benar->id],
        ]);

        AttemptQuestion::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'urutan' => 2,
            'urutan_opsi_json' => [],
        ]);

        if ($denganJawaban) {
            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $pg->id,
                'option_id' => $benar->id,
                'waktu_jawab_ms' => 1000,
                'skor' => 0,
                'answered_at' => now(),
            ]);

            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $essay->id,
                'jawaban_teks' => 'HTTP tidak terenkripsi, HTTPS memakai TLS.',
                'waktu_jawab_ms' => 2000,
                'skor' => 0,
                'answered_at' => now(),
            ]);
        }

        return [$admin, $peserta, $session, $attempt, $pg, $essay, $benar];
    }
}
