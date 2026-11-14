<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QuizQuestionScopedTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_quiz_first_then_adds_question_to_that_quiz(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kuis',
            'username' => 'admin-quiz-test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-token',
        ]);

        $kelas = Kelas::create([
            'nama' => 'Kelas TI A',
            'pin' => '654321',
            'created_by' => $admin->id,
        ]);

        // 1. Create quiz requiring kelas_id
        $responseQuiz = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->post(route('admin.quizzes.store'), [
                'kelas_id' => $kelas->id,
                'judul' => 'Kuis Algoritma & Struktur Data',
                'jumlah_soal' => null,
            ]);

        $quiz = Quiz::where('judul', 'Kuis Algoritma & Struktur Data')->first();
        $this->assertNotNull($quiz);
        $this->assertEquals($kelas->id, $quiz->kelas_id);
        $responseQuiz->assertRedirect(route('admin.quizzes.questions.index', $quiz));

        // 2. Add question scoped to this quiz
        $responseQuestion = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-token'])
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'teks' => 'Berapa kompleksitas waktu binary search?',
                'tipe' => 'pilihan_ganda',
                'bobot' => 5,
                'durasi_detik' => 45,
                'correct' => 0,
                'opsi' => [
                    ['teks' => 'O(log n)'],
                    ['teks' => 'O(n)'],
                    ['teks' => 'O(n^2)'],
                ],
            ]);

        $responseQuestion->assertRedirect(route('admin.quizzes.questions.index', $quiz));

        $question = Question::where('teks', 'Berapa kompleksitas waktu binary search?')->first();
        $this->assertNotNull($question);
        $this->assertEquals($quiz->id, $question->quiz_id);
        $this->assertEquals(1, $quiz->questions()->count());
        $this->assertEquals(3, $question->options()->count());
        $this->assertTrue($question->correctOption()->is_correct);
        $this->assertEquals('O(log n)', $question->correctOption()->teks);
    }

    public function test_question_from_other_quiz_cannot_be_edited_or_deleted_via_mismatched_quiz_route(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kuis',
            'username' => 'admin-quiz-scope',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-scope-token',
        ]);

        $kelas1 = Kelas::create(['nama' => 'Kelas 1', 'pin' => '111111', 'created_by' => $admin->id]);
        $kelas2 = Kelas::create(['nama' => 'Kelas 2', 'pin' => '222222', 'created_by' => $admin->id]);

        $quiz1 = Quiz::create(['kelas_id' => $kelas1->id, 'judul' => 'Kuis 1', 'created_by' => $admin->id]);
        $quiz2 = Quiz::create(['kelas_id' => $kelas2->id, 'judul' => 'Kuis 2', 'created_by' => $admin->id]);

        $question1 = Question::create([
            'quiz_id' => $quiz1->id,
            'teks' => 'Soal milik kuis 1',
            'tipe' => 'pilihan_ganda',
            'bobot' => 1,
            'durasi_detik' => 30,
        ]);

        // Access question 1 under quiz 2 -> must 404
        $resEdit = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-scope-token'])
            ->get(route('admin.quizzes.questions.edit', [$quiz2, $question1]));

        $resEdit->assertNotFound();

        // Update question 1 under quiz 2 -> must 404
        $resUpdate = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-scope-token'])
            ->put(route('admin.quizzes.questions.update', [$quiz2, $question1]), [
                'teks' => 'Diubah lewat kuis 2',
                'tipe' => 'pilihan_ganda',
                'bobot' => 1,
                'durasi_detik' => 30,
                'correct' => 0,
                'opsi' => [['teks' => 'A'], ['teks' => 'B']],
            ]);

        $resUpdate->assertNotFound();
        $this->assertEquals('Soal milik kuis 1', $question1->fresh()->teks);

        // Delete question 1 under quiz 2 -> must 404
        $resDelete = $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-scope-token'])
            ->delete(route('admin.quizzes.questions.destroy', [$quiz2, $question1]));

        $resDelete->assertNotFound();
        $this->assertFalse($question1->fresh()->trashed());
    }

    public function test_admin_dapat_menyimpan_rubrik_essay_dengan_total_sesuai_nilai_maksimal(): void
    {
        $admin = User::create([
            'nama' => 'Admin Rubrik',
            'username' => 'admin-rubrik',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-rubrik-token',
        ]);
        $quiz = Quiz::create(['judul' => 'Kuis Rubrik', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'teks' => 'Jelaskan perlindungan data saat transit.',
                'tipe' => 'essay',
                'bobot' => 10,
                'durasi_detik' => 120,
                'kunci_essay' => 'Data dilindungi dengan TLS.',
                'rubrik_essay' => [
                    ['label' => 'Menjelaskan tujuan TLS', 'points' => 4],
                    ['label' => 'Menjelaskan enkripsi transit', 'points' => 6],
                ],
            ])
            ->assertRedirect();

        $question = Question::where('teks', 'Jelaskan perlindungan data saat transit.')->firstOrFail();
        $this->assertSame('Data dilindungi dengan TLS.', $question->kunci_essay);
        $this->assertSame([
            ['label' => 'Menjelaskan tujuan TLS', 'points' => 4],
            ['label' => 'Menjelaskan enkripsi transit', 'points' => 6],
        ], $question->rubrik_essay);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'teks' => 'Rubrik tidak seimbang.',
                'tipe' => 'essay',
                'bobot' => 10,
                'durasi_detik' => 120,
                'rubrik_essay' => [
                    ['label' => 'Kriteria tunggal', 'points' => 5],
                ],
            ])
            ->assertSessionHasErrors('rubrik_essay');
    }

    public function test_admin_dapat_menyimpan_petunjuk_dan_sub_soal_essay(): void
    {
        $admin = User::create([
            'nama' => 'Admin Sub Soal',
            'username' => 'admin-sub-soal',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-sub-soal-token',
        ]);
        $quiz = Quiz::create(['judul' => 'Kuis Sub Soal', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'teks' => 'Topologi dan media transmisi jaringan.',
                'tipe' => 'essay',
                'bobot' => 20,
                'durasi_detik' => 300,
                'petunjuk' => "Topologi jaringan\nMedia transmisi",
                'sub_soal' => [
                    [
                        'label' => 'A',
                        'teks' => 'Jelaskan perbedaan topologi bus, star, dan ring.',
                        'petunjuk' => "Kelebihan\nKekurangan",
                    ],
                    [
                        'label' => 'B',
                        'teks' => 'Pilih topologi dan media untuk kampus.',
                        'petunjuk' => "Topologi\nMedia\nAlasan",
                    ],
                ],
            ])
            ->assertRedirect();

        $question = Question::where('teks', 'Topologi dan media transmisi jaringan.')->firstOrFail();

        $this->assertSame(['Topologi jaringan', 'Media transmisi'], $question->petunjuk_json);
        $this->assertSame([
            [
                'label' => 'A',
                'teks' => 'Jelaskan perbedaan topologi bus, star, dan ring.',
                'petunjuk' => ['Kelebihan', 'Kekurangan'],
            ],
            [
                'label' => 'B',
                'teks' => 'Pilih topologi dan media untuk kampus.',
                'petunjuk' => ['Topologi', 'Media', 'Alasan'],
            ],
        ], $question->sub_soal_json);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'teks' => 'Sub-soal duplikat.',
                'tipe' => 'essay',
                'bobot' => 10,
                'durasi_detik' => 120,
                'sub_soal' => [
                    ['label' => 'A', 'teks' => 'Pertanyaan pertama.'],
                    ['label' => 'A', 'teks' => 'Pertanyaan kedua.'],
                ],
            ])
            ->assertSessionHasErrors('sub_soal.0.label');
    }

    public function test_deleting_quiz_cascades_to_its_questions(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kuis',
            'username' => 'admin-quiz-del',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-del-token',
        ]);

        $kelas = Kelas::create(['nama' => 'Kelas Hapus', 'pin' => '333333', 'created_by' => $admin->id]);
        $quiz = Quiz::create(['kelas_id' => $kelas->id, 'judul' => 'Kuis Dihapus', 'created_by' => $admin->id]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Soal kuis akan dihapus',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);

        $this->actingAs($admin)
            ->withSession(['auth_token' => 'admin-del-token'])
            ->delete(route('admin.quizzes.destroy', $quiz));

        $this->assertDatabaseMissing('quizzes', ['id' => $quiz->id]);
        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
    }

    public function test_admin_menghapus_beberapa_soal_sekaligus_dan_melewati_soal_terkunci(): void
    {
        $admin = User::create([
            'nama' => 'Admin Bulk',
            'username' => 'admin-quiz-bulk',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-bulk-token',
        ]);

        $kelas1 = Kelas::create(['nama' => 'Kelas Bulk 1', 'pin' => '444444', 'created_by' => $admin->id]);
        $kelas2 = Kelas::create(['nama' => 'Kelas Bulk 2', 'pin' => '555555', 'created_by' => $admin->id]);

        $quiz1 = Quiz::create(['kelas_id' => $kelas1->id, 'judul' => 'Kuis Bulk', 'created_by' => $admin->id]);
        $quiz2 = Quiz::create(['kelas_id' => $kelas2->id, 'judul' => 'Kuis Lain', 'created_by' => $admin->id]);

        $bisaDihapus1 = Question::create([
            'quiz_id' => $quiz1->id,
            'teks' => 'Soal bisa dihapus satu',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);
        $bisaDihapus2 = Question::create([
            'quiz_id' => $quiz1->id,
            'teks' => 'Soal bisa dihapus dua',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);
        $terkunci = Question::create([
            'quiz_id' => $quiz1->id,
            'teks' => 'Soal terkunci',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);
        $milikKuisLain = Question::create([
            'quiz_id' => $quiz2->id,
            'teks' => 'Soal milik kuis lain',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);

        $sesiLain = QuizSession::create([
            'quiz_id' => $quiz2->id,
            'kelas_id' => $kelas2->id,
            'nama' => 'Sesi Lain',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'token-bulk-lain',
            'created_by' => $admin->id,
        ]);

        SessionQuestion::create([
            'quiz_session_id' => $sesiLain->id,
            'question_id' => $terkunci->id,
            'urutan' => 1,
        ]);

        $halaman = $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get(route('admin.quizzes.questions.index', $quiz1));

        $halaman->assertOk();
        $halaman->assertSee('Pilih semua');
        $halaman->assertSee('form-hapus-soal-massal');
        $this->assertSame(2, substr_count($halaman->getContent(), 'name="question_ids[]"'));

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->delete(route('admin.quizzes.questions.bulk-destroy', $quiz1), [
                'question_ids' => [
                    $bisaDihapus1->id,
                    $bisaDihapus2->id,
                    $terkunci->id,
                    $milikKuisLain->id,
                ],
            ]);

        $response->assertRedirect(route('admin.quizzes.questions.index', $quiz1));
        $response->assertSessionHas('sukses', fn (string $pesan) => str_contains($pesan, '2 soal dihapus')
            && str_contains($pesan, '2 soal dilewati'));

        $this->assertSoftDeleted('questions', ['id' => $bisaDihapus1->id]);
        $this->assertSoftDeleted('questions', ['id' => $bisaDihapus2->id]);
        $this->assertNotSoftDeleted('questions', ['id' => $terkunci->id]);
        $this->assertNotSoftDeleted('questions', ['id' => $milikKuisLain->id]);
    }

    public function test_bulk_hapus_menolak_jika_semua_soal_terkunci(): void
    {
        $admin = User::create([
            'nama' => 'Admin Bulk Terkunci',
            'username' => 'admin-quiz-bulk-locked',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-bulk-locked-token',
        ]);

        $kelas = Kelas::create(['nama' => 'Kelas Terkunci', 'pin' => '666666', 'created_by' => $admin->id]);
        $quiz = Quiz::create(['kelas_id' => $kelas->id, 'judul' => 'Kuis Terkunci', 'created_by' => $admin->id]);

        $soal1 = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Soal terkunci satu',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);
        $soal2 = Question::create([
            'quiz_id' => $quiz->id,
            'teks' => 'Soal terkunci dua',
            'tipe' => 'essay',
            'bobot' => 10,
            'durasi_detik' => 60,
        ]);

        QuizSession::create([
            'quiz_id' => $quiz->id,
            'kelas_id' => $kelas->id,
            'nama' => 'Sesi Terkunci',
            'mode' => 'acak',
            'status' => 'selesai',
            'display_token' => 'token-bulk-terkunci',
            'created_by' => $admin->id,
        ]);

        $halaman = $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->get(route('admin.quizzes.questions.index', $quiz));

        $halaman->assertOk();
        $halaman->assertDontSee('Pilih semua');
        $this->assertSame(0, substr_count($halaman->getContent(), 'name="question_ids[]"'));

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->delete(route('admin.quizzes.questions.bulk-destroy', $quiz), [
                'question_ids' => [$soal1->id, $soal2->id],
            ]);

        $response->assertSessionHasErrors('questions');
        $this->assertNotSoftDeleted('questions', ['id' => $soal1->id]);
        $this->assertNotSoftDeleted('questions', ['id' => $soal2->id]);
    }

    public function test_bulk_hapus_menolak_pilihan_kosong(): void
    {
        $admin = User::create([
            'nama' => 'Admin Bulk Kosong',
            'username' => 'admin-quiz-bulk-empty',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-bulk-empty-token',
        ]);

        $kelas = Kelas::create(['nama' => 'Kelas Kosong', 'pin' => '777777', 'created_by' => $admin->id]);
        $quiz = Quiz::create(['kelas_id' => $kelas->id, 'judul' => 'Kuis Kosong', 'created_by' => $admin->id]);

        $response = $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token])
            ->delete(route('admin.quizzes.questions.bulk-destroy', $quiz), [
                'question_ids' => [],
            ]);

        $response->assertSessionHasErrors('question_ids');
    }

    public function test_participant_must_enroll_class_to_see_quizzes(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kuis',
            'username' => 'admin-quiz-enroll',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'current_session_token' => 'admin-enroll-token',
        ]);

        $peserta = User::create([
            'nama' => 'Peserta Uji',
            'username' => 'peserta-enroll',
            'password' => Hash::make('password'),
            'role' => 'peserta',
            'current_session_token' => 'peserta-enroll-token',
        ]);

        $kelas = Kelas::create([
            'nama' => 'Pemrograman Web',
            'pin' => '998877',
            'created_by' => $admin->id,
        ]);

        $quiz = Quiz::create([
            'kelas_id' => $kelas->id,
            'judul' => 'Ujian Tengah Semester',
            'created_by' => $admin->id,
        ]);

        // Sebelum enroll: halaman kelas hanya menampilkan form PIN, kuis tidak muncul
        $resBelum = $this->actingAs($peserta)
            ->withSession(['auth_token' => 'peserta-enroll-token'])
            ->get(route('peserta.kelas.show', $kelas));

        $resBelum->assertOk();
        $resBelum->assertSee('PIN kelas');
        $resBelum->assertDontSee('Ujian Tengah Semester');

        // Peserta enroll dengan PIN
        $resMasuk = $this->actingAs($peserta)
            ->withSession(['auth_token' => 'peserta-enroll-token'])
            ->post(route('peserta.kelas.masuk', $kelas), ['pin' => '998877']);

        $resMasuk->assertRedirect(route('peserta.kelas.show', $kelas));

        // Setelah enroll: kuis kelas terlihat
        $resSudah = $this->actingAs($peserta)
            ->withSession(['auth_token' => 'peserta-enroll-token'])
            ->get(route('peserta.kelas.show', $kelas));

        $resSudah->assertOk();
        $resSudah->assertSee('Ujian Tengah Semester');
    }
}
