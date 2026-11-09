<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TrialQuizSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->orderBy('id')->first();

        if ($admin === null) {
            return;
        }

        $quiz = Quiz::updateOrCreate(
            ['judul' => 'Kuis Uji Coba'],
            [
                'kelas_id' => null,
                'jumlah_soal' => 3,
                'acak_opsi' => true,
                'created_by' => $admin->id,
            ],
        );

        $soal = [
            [
                'teks' => 'Apa langkah pertama untuk mengikuti kuis di STIKOM Quiz?',
                'pembahasan' => 'Kartu sesi yang dibuka admin muncul di Beranda. Tekan Gabung sesi sebelum memulai ujian.',
                'opsi' => [
                    ['teks' => 'Tekan Gabung sesi pada kartu sesi', 'benar' => true],
                    ['teks' => 'Tutup halaman dashboard', 'benar' => false],
                    ['teks' => 'Keluar dari akun', 'benar' => false],
                    ['teks' => 'Membuka menu Panduan', 'benar' => false],
                ],
            ],
            [
                'teks' => 'Saat mulai mengerjakan soal, mode apa yang perlu diaktifkan?',
                'pembahasan' => 'Layar penuh membantu mencegah peserta membuka aplikasi lain selama ujian.',
                'opsi' => [
                    ['teks' => 'Mode layar penuh', 'benar' => true],
                    ['teks' => 'Mode gelap', 'benar' => false],
                    ['teks' => 'Mode pesawat', 'benar' => false],
                    ['teks' => 'Mode berbagi layar', 'benar' => false],
                ],
            ],
            [
                'teks' => 'Apa yang terjadi setelah memilih jawaban pilihan ganda?',
                'pembahasan' => 'Jawaban disimpan otomatis ke server, jadi Anda tidak perlu menekan tombol simpan.',
                'opsi' => [
                    ['teks' => 'Jawaban tersimpan otomatis', 'benar' => true],
                    ['teks' => 'Jawaban hilang saat pindah soal', 'benar' => false],
                    ['teks' => 'Harus menekan tombol simpan', 'benar' => false],
                    ['teks' => 'Jawaban dikirim lewat email', 'benar' => false],
                ],
            ],
        ];

        foreach ($soal as $data) {
            $question = Question::updateOrCreate(
                ['quiz_id' => $quiz->id, 'teks' => $data['teks']],
                [
                    'tipe' => 'pilihan_ganda',
                    'durasi_detik' => 60,
                    'bobot' => 10,
                    'pembahasan' => $data['pembahasan'],
                    'kunci_essay' => null,
                ],
            );

            $question->options()->delete();

            foreach ($data['opsi'] as $opsi) {
                $question->options()->create([
                    'teks' => $opsi['teks'],
                    'is_correct' => $opsi['benar'],
                ]);
            }
        }

        $session = QuizSession::firstOrCreate(
            ['quiz_id' => $quiz->id, 'nama' => 'Uji Coba Sistem'],
            [
                'mode' => 'acak',
                'acak_opsi' => true,
                'tampilkan_pembahasan' => true,
                'pembahasan_mode' => 'terbuka',
                'leaderboard_live' => true,
                'max_peserta' => null,
                'jeda_detik' => 5,
                'status' => 'berjalan',
                'display_token' => Str::random(48),
                'dibuka_at' => now(),
                'created_by' => $admin->id,
            ],
        );

        if ($session->status !== 'berjalan') {
            $session->update([
                'status' => 'berjalan',
                'fase' => null,
                'soal_aktif_urutan' => null,
                'soal_mulai_at' => null,
                'soal_berakhir_at' => null,
                'ditutup_at' => null,
                'dibuka_at' => now(),
            ]);
        }
    }
}
