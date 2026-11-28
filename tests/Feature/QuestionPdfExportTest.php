<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Option;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionMedia;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuestionPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pratinjau_dan_unduhan_pdf_soal_mendukung_varian_peserta_pengajar_cpmk_dan_media(): void
    {
        Storage::fake('local');

        [$organization, $admin] = $this->buatTenant('politeknik-negeri', 'Politeknik Negeri Uji', 'dosen-pdf');

        $kelas = Kelas::create([
            'organization_id' => $organization->id,
            'nama' => 'TI-2A Keamanan Informasi',
            'pin' => 'PDF2A',
            'created_by' => $admin->id,
        ]);

        $quiz = Quiz::create([
            'organization_id' => $organization->id,
            'kelas_id' => $kelas->id,
            'judul' => 'Ujian Tengah Semester Keamanan Informasi',
            'created_by' => $admin->id,
        ]);

        $pg = Question::create([
            'quiz_id' => $quiz->id,
            'organization_id' => $organization->id,
            'teks' => 'Algoritma hashing kriptografis yang direkomendasikan saat ini adalah?',
            'tipe' => 'pilihan_ganda',
            'bobot' => 30,
            'durasi_detik' => 60,
            'pembahasan' => 'SHA-256 tahan terhadap serangan kolisi praktis.',
        ]);

        $pngOnePixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4z8DwHwAFAAH/iZk9HQAAAABJRU5ErkJggg==');
        $mediaPath = "question-media/{$organization->id}/skema-hash.png";
        Storage::disk('local')->put($mediaPath, $pngOnePixel);

        $media = QuestionMedia::create([
            'organization_id' => $organization->id,
            'question_id' => $pg->id,
            'path' => $mediaPath,
            'mime_type' => 'image/png',
            'size_bytes' => strlen($pngOnePixel),
            'original_name' => 'skema-hash.png',
        ]);

        $pg->update([
            'content_blocks_json' => [
                ['type' => 'image', 'media_id' => $media->id, 'alt' => 'Diagram fungsi hash'],
            ],
        ]);

        Option::create(['question_id' => $pg->id, 'teks' => 'SHA-256', 'is_correct' => true]);
        Option::create(['question_id' => $pg->id, 'teks' => 'MD5', 'is_correct' => false]);

        Question::create([
            'quiz_id' => $quiz->id,
            'organization_id' => $organization->id,
            'teks' => 'Analisis celah keamanan pada penyimpanan password tanpa salt.',
            'tipe' => 'essay',
            'bobot' => 70,
            'durasi_detik' => 300,
            'kunci_essay' => 'Rentan terhadap rainbow table dan precomputed hash attack.',
            'rubrik_essay' => [
                ['kriteria' => 'Menjelaskan risiko rainbow table', 'skor' => 35],
                ['kriteria' => 'Menjelaskan mitigasi unik per pengguna', 'skor' => 35],
            ],
        ]);

        $sessionData = [
            'auth_token' => $admin->current_session_token,
            'organization_id' => $organization->id,
        ];

        $pesertaPreview = $this->actingAs($admin)
            ->withSession($sessionData)
            ->get(route('admin.quizzes.questions.pdf.preview', [
                'quiz' => $quiz,
                'varian' => 'peserta',
            ]));

        $pesertaPreview->assertOk()
            ->assertSee('POLITEKNIK NEGERI UJI')
            ->assertSee('Nama Peserta:')
            ->assertSee('data:image/png;base64,', false)
            ->assertDontSee('KUNCI JAWABAN')
            ->assertDontSee('SHA-256 tahan terhadap serangan kolisi praktis.')
            ->assertDontSee('Menjelaskan risiko rainbow table')
            ->assertDontSee('Capaian Pembelajaran Mata Kuliah');

        $this->actingAs($admin)
            ->withSession($sessionData)
            ->get(route('admin.quizzes.index'))
            ->assertOk()
            ->assertSee(route('admin.quizzes.duplicate', $quiz), false)
            ->assertSee('Duplikat');

        $this->actingAs($admin)
            ->withSession($sessionData)
            ->get(route('admin.quizzes.questions.index', $quiz))
            ->assertOk()
            ->assertSee(route('admin.quizzes.questions.pdf.preview', $quiz), false)
            ->assertSee('Ekspor PDF')
            ->assertSee('Duplikat Kuis');

        $this->actingAs($admin)
            ->withSession($sessionData)
            ->get(route('admin.sessions.create', ['quiz_id' => $quiz->id]))
            ->assertOk()
            ->assertSee('Kuis ini memuat soal essay, sehingga hanya dapat dijalankan pada Mode Acak.');

        $this->actingAs($admin)
            ->withSession($sessionData)
            ->from(route('admin.quizzes.questions.pdf.preview', $quiz))
            ->followingRedirects()
            ->get(route('admin.quizzes.questions.pdf.preview', ['quiz' => $quiz, 'waktu_menit' => 999]))
            ->assertOk()
            ->assertSee('Pengaturan naskah belum valid:');

        $pengajarPreview = $this->actingAs($admin)
            ->withSession($sessionData)
            ->get(route('admin.quizzes.questions.pdf.preview', [
                'quiz' => $quiz,
                'varian' => 'pengajar',
                'kode_cpmk' => 'CPMK-04',
            ]));

        $pengajarPreview->assertOk()
            ->assertSee('ARSIP PENGAJAR')
            ->assertSee('CPMK-04')
            ->assertSee('KUNCI JAWABAN')
            ->assertSee('SHA-256 tahan terhadap serangan kolisi praktis.')
            ->assertSee('Rentan terhadap rainbow table dan precomputed hash attack.')
            ->assertSee('Menjelaskan risiko rainbow table');

        $download = $this->actingAs($admin)
            ->withSession($sessionData)
            ->get(route('admin.quizzes.questions.pdf.download', [
                'quiz' => $quiz,
                'varian' => 'peserta',
            ]));

        $download->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $download->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', (string) $download->getContent());
    }

    public function test_kuis_kosong_dan_isolasi_antar_tenant_pada_ekspor_pdf(): void
    {
        [$organization, $admin] = $this->buatTenant('kampus-kosong', 'Kampus Kosong', 'admin-kosong');
        [$otherOrg, $otherAdmin] = $this->buatTenant('kampus-seberang', 'Kampus Seberang', 'admin-seberang');

        $emptyQuiz = Quiz::create([
            'organization_id' => $organization->id,
            'judul' => 'Kuis Belum Ada Soal',
            'created_by' => $admin->id,
        ]);

        $sessionData = [
            'auth_token' => $admin->current_session_token,
            'organization_id' => $organization->id,
        ];

        $this->actingAs($admin)
            ->withSession($sessionData)
            ->get(route('admin.quizzes.questions.pdf.preview', $emptyQuiz))
            ->assertOk()
            ->assertSee('Belum ada soal untuk dicetak');

        $this->actingAs($admin)
            ->withSession($sessionData)
            ->get(route('admin.quizzes.questions.pdf.download', $emptyQuiz))
            ->assertStatus(422);

        $otherSession = [
            'auth_token' => $otherAdmin->current_session_token,
            'organization_id' => $otherOrg->id,
        ];

        $this->actingAs($otherAdmin)
            ->withSession($otherSession)
            ->get(route('admin.quizzes.questions.pdf.preview', $emptyQuiz))
            ->assertNotFound();

        $this->actingAs($otherAdmin)
            ->withSession($otherSession)
            ->get(route('admin.quizzes.questions.pdf.download', $emptyQuiz))
            ->assertNotFound();
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function buatTenant(string $slug, string $name, string $username): array
    {
        $organization = Organization::create([
            'name' => $name,
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $admin = User::create([
            'nama' => ucfirst($username),
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => $username.'-token',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $organization->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'admin', 'status' => 'active'],
        ]);

        return [$organization, $admin];
    }
}
