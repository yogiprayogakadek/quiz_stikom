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
            ->assertSee('Pilih media transmisi.');
    }

    public function test_laporan_admin_menampilkan_jawaban_essay_per_sub_soal(): void
    {
        [$admin, $peserta, $session, $attempt, $pg, $essay] = $this->buatAcakBerjalan(false);

        $essay->update([
            'sub_soal_json' => [
                ['label' => 'A', 'teks' => 'Jelaskan topologi jaringan.', 'petunjuk' => []],
                ['label' => 'B', 'teks' => 'Pilih media transmisi.', 'petunjuk' => ['Antargedung', 'Dalam lab']],
            ],
        ]);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $essay->id,
            'jawaban_teks' => "A:\nTopologi star.\n\nB:\nFiber optik.",
            'jawaban_parts_json' => [
                'A' => 'Topologi star.',
                'B' => 'Fiber optik.',
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
