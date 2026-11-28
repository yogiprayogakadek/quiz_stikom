<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionMedia;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuickPasteAndQuestionBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_tempel_cepat_pratinjau_dan_simpan_soal_pilihan_ganda_serta_essay(): void
    {
        [$org, $admin, $quiz] = $this->setupWorkspaceAndQuiz('alpha', 'admin_alpha', 'Kuis Jaringan');

        $naskah = <<<'TEXT'
1. Protokol yang menerjemahkan nama domain menjadi alamat IP adalah?
A. FTP
*B. DNS
C. SMTP
D. DHCP
Pembahasan: DNS memetakan nama domain ke alamat IP.

2. [Essay | Bobot: 15] Jelaskan perbedaan mendasar antara TCP dan UDP pada lapisan transport.
Kunci: TCP berorientasi koneksi dan menjamin urutan paket, sedangkan UDP tanpa koneksi dan lebih ringan.
Pembahasan: TCP cocok untuk akurasi data, UDP cocok untuk streaming real-time.
TEXT;

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $org->id])
            ->postJson(route('admin.quizzes.questions.quick-paste.preview', $quiz), [
                'raw_text' => $naskah,
            ])
            ->assertOk()
            ->assertJsonCount(2, 'valid')
            ->assertJsonCount(0, 'errors')
            ->assertJsonPath('valid.0.tipe', 'pilihan_ganda')
            ->assertJsonPath('valid.0.correct', 1)
            ->assertJsonPath('valid.1.tipe', 'essay')
            ->assertJsonPath('valid.1.bobot', 15);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $org->id])
            ->post(route('admin.quizzes.questions.quick-paste.store', $quiz), [
                'raw_text' => $naskah,
            ])
            ->assertRedirect(route('admin.quizzes.questions.index', $quiz));

        $this->assertSame(2, $quiz->questions()->count());

        $soalPg = $quiz->questions()->where('tipe', 'pilihan_ganda')->with('options')->firstOrFail();
        $this->assertCount(4, $soalPg->options);
        $this->assertSame('DNS', $soalPg->options->firstWhere('is_correct', true)?->teks);

        $soalEssay = $quiz->questions()->where('tipe', 'essay')->firstOrFail();
        $this->assertSame(15, $soalEssay->bobot);
        $this->assertStringContainsString('TCP berorientasi koneksi', (string) $soalEssay->kunci_essay);
    }

    public function test_impor_csv_mendukung_pemisah_titik_koma_excel_indonesia(): void
    {
        [$org, $admin, $quiz] = $this->setupWorkspaceAndQuiz('alpha', 'admin_alpha', 'Kuis Basis Data');

        $csvSemicolon = implode("\n", [
            'teks;tipe;bobot;opsi_a;opsi_b;opsi_c;opsi_d;jawaban_benar;durasi_detik;pembahasan',
            '"Perintah SQL untuk mengambil data adalah?";pilihan_ganda;2;INSERT;SELECT;UPDATE;DELETE;B;45;"SELECT membaca baris tabel."',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal-indo.csv', $csvSemicolon);

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $org->id])
            ->post(route('admin.quizzes.questions.import.store', $quiz), [
                'file' => $file,
            ])
            ->assertRedirect(route('admin.quizzes.questions.import.result', $quiz));

        $soal = $quiz->questions()->with('options')->first();
        $this->assertNotNull($soal);
        $this->assertSame('SELECT', $soal->options->firstWhere('is_correct', true)?->teks);
    }

    public function test_simpan_dan_tambah_soal_lagi_mengarahkan_kembali_ke_form_tambah(): void
    {
        [$org, $admin, $quiz] = $this->setupWorkspaceAndQuiz('alpha', 'admin_alpha', 'Kuis Algoritma');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $org->id])
            ->get(route('admin.quizzes.questions.create', $quiz))
            ->assertOk()
            ->assertSee('Simpan & tambah soal lagi');

        $this->actingAs($admin)
            ->withSession(['auth_token' => $admin->current_session_token, 'organization_id' => $org->id])
            ->post(route('admin.quizzes.questions.store', $quiz), [
                'teks' => 'Berapa kompleksitas pencarian biner?',
                'tipe' => 'pilihan_ganda',
                'bobot' => 2,
                'durasi_detik' => 30,
                'opsi' => [
                    ['teks' => 'O(1)'],
                    ['teks' => 'O(log n)'],
                    ['teks' => 'O(n)'],
                    ['teks' => 'O(n^2)'],
                ],
                'correct' => 1,
                'after_save' => 'create_another',
            ])
            ->assertRedirect(route('admin.quizzes.questions.create', ['quiz' => $quiz, 'tipe' => 'pilihan_ganda']));

        $this->assertSame(1, $quiz->questions()->count());
    }

    public function test_ambil_soal_dari_kuis_lain_menyalin_media_dan_menolak_lintas_organisasi(): void
    {
        Storage::fake('local');

        [$orgAlpha, $adminAlpha, $quizSumber] = $this->setupWorkspaceAndQuiz('alpha', 'admin_alpha', 'Bank Soal Jaringan');
        $quizTujuan = Quiz::create([
            'organization_id' => $orgAlpha->id,
            'judul' => 'UTS Jaringan',
            'jumlah_soal' => null,
            'acak_opsi' => true,
            'created_by' => $adminAlpha->id,
        ]);

        [$orgBeta, $adminBeta, $quizBeta] = $this->setupWorkspaceAndQuiz('beta', 'admin_beta', 'Kuis Kampus Lain');

        $soalSumber = Question::withoutGlobalScopes()->create([
            'organization_id' => $orgAlpha->id,
            'quiz_id' => $quizSumber->id,
            'teks' => 'Perhatikan topologi berikut, tentukan subnet mask yang tepat.',
            'tipe' => 'pilihan_ganda',
            'durasi_detik' => 60,
            'bobot' => 5,
        ]);
        $soalSumber->options()->createMany([
            ['teks' => '255.255.255.0', 'is_correct' => true],
            ['teks' => '255.255.0.0', 'is_correct' => false],
            ['teks' => '255.0.0.0', 'is_correct' => false],
            ['teks' => '255.255.255.252', 'is_correct' => false],
        ]);

        $pathAsli = 'question-media/'.$orgAlpha->id.'/topologi-asli.png';
        Storage::disk('local')->put($pathAsli, 'fake-image-binary-content');
        $mediaAsli = QuestionMedia::withoutGlobalScopes()->create([
            'organization_id' => $orgAlpha->id,
            'question_id' => $soalSumber->id,
            'path' => $pathAsli,
            'mime_type' => 'image/png',
            'size_bytes' => strlen('fake-image-binary-content'),
            'original_name' => 'topologi.png',
        ]);
        $soalSumber->update([
            'content_blocks_json' => [
                [
                    'type' => 'image',
                    'media_id' => $mediaAsli->id,
                    'alt' => 'Topologi jaringan cabang',
                    'caption' => 'Gambar 1',
                ],
            ],
        ]);

        $soalLintasOrg = Question::withoutGlobalScopes()->create([
            'organization_id' => $orgBeta->id,
            'quiz_id' => $quizBeta->id,
            'teks' => 'Soal Rahasia Organisasi Beta',
            'tipe' => 'essay',
            'durasi_detik' => 120,
            'bobot' => 10,
        ]);

        $this->actingAs($adminAlpha)
            ->withSession(['auth_token' => $adminAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->get(route('admin.quizzes.questions.bank', $quizTujuan))
            ->assertOk()
            ->assertSee('Perhatikan topologi berikut')
            ->assertDontSee('Soal Rahasia Organisasi Beta');

        $this->actingAs($adminAlpha)
            ->withSession(['auth_token' => $adminAlpha->current_session_token, 'organization_id' => $orgAlpha->id])
            ->post(route('admin.quizzes.questions.bank.copy', $quizTujuan), [
                'question_ids' => [$soalSumber->id, $soalLintasOrg->id],
            ])
            ->assertRedirect(route('admin.quizzes.questions.index', $quizTujuan));

        $this->assertSame(1, $quizTujuan->questions()->count());
        $salinan = $quizTujuan->questions()->with(['options', 'media'])->firstOrFail();
        $this->assertSame($soalSumber->teks, $salinan->teks);
        $this->assertCount(4, $salinan->options);
        $this->assertCount(1, $salinan->media);
        $this->assertNotSame($mediaAsli->path, $salinan->media->first()->path);
        Storage::disk('local')->assertExists($salinan->media->first()->path);
    }

    private function setupWorkspaceAndQuiz(string $slug, string $username, string $judulKuis): array
    {
        $org = Organization::create([
            'name' => 'Org '.ucfirst($slug),
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => ucfirst($username),
            'username' => $username,
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => $username.'-token',
        ]);

        $org->users()->attach($admin->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);

        $quiz = Quiz::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'judul' => $judulKuis,
            'jumlah_soal' => null,
            'acak_opsi' => true,
            'created_by' => $admin->id,
        ]);

        return [$org, $admin, $quiz];
    }
}
