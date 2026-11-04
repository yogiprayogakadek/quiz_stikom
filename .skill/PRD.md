# Paket Vibe Coding: Website Kuis

Nov 2, 2026 · @Yogi Prayoga

Paket ini mengubah PRD menjadi 7 prompt berurutan yang bisa ditempel ke alat vibe coding, lengkap dengan keputusan final, konteks proyek, dan kriteria penerimaan per fase.

## Cara memakai

1. Buat folder proyek kosong dan jalankan `git init`. Simpan blok **Konteks Proyek** di bawah sebagai file `AGENTS.md` (atau `CLAUDE.md`, atau aturan proyek di Cursor) agar dibaca setiap sesi.
2. Tempel **satu prompt fase per sesi**, berurutan dari Fase 0. Jangan menggabung fase.
3. Setelah tiap fase, uji kriteria penerimaannya secara manual, lalu commit sebelum lanjut.
4. Jika AI menyimpang dari Konteks Proyek (misalnya mengirim `is_correct` ke browser), minta perbaiki dulu sebelum fase berikutnya.

## Keputusan final

| # | Keputusan | Dampak pada rancangan |
| --- | --- | --- |
| 1 | Mode dikunci | Mode tidak bisa diubah setelah sesi keluar dari status draft, dicek di server |
| 2 | Durasi diatur per soal | Kolom `questions.durasi_detik` |
| 3 | Mode Serempak lanjut otomatis | Siklus soal digerakkan job tertunda di server |
| 4 | Opsi diacak; admin memilih Serempak atau Acak | Opsi diacak per peserta di kedua mode |
| 5 | Leaderboard ditampilkan | Di proyektor; aturan isi per mode ada di bawah |
| 6 | Peserta tanpa batas bawaan | `max_peserta` opsional di pengaturan sesi |
| 7 | Server di LAN | Tanpa ketergantungan internet; Reverb dikonfigurasi dengan IP LAN |
| 8 | Akun peserta: impor CSV dan manual | Login memakai username, bukan email |
| 9 | Pembahasan tampil atau tidak, sesuai admin | Opsi `tampilkan_pembahasan` per sesi |
| 10 | Tidak boleh mencoba ulang | Unique `(user_id, quiz_session_id)` di database |

## Asumsi saya yang perlu Anda cek

Bagian ini adalah tebakan saya untuk celah di jawaban Anda. Koreksi sebelum mulai Fase 0, karena sebagian mengubah skema.

| # | Asumsi | Kenapa saya ambil |
| --- | --- | --- |
| A | Stack: Laravel + MySQL + Laravel Reverb | Anda belum memilih stack. Alternatif: Node.js + Socket.IO. Versi dan batasan Reverb perlu diverifikasi di dokumentasi resmi saat dipasang. |
| B | Soal Serempak dianggap selesai bila timer habis **atau** semua peserta yang bergabung sudah menjawab; lalu jeda 5 detik (bisa diatur) menampilkan jawaban benar dan leaderboard, kemudian lanjut otomatis | Anda minta lanjut otomatis; tanpa jeda, peserta tidak sempat melihat hasil soal |
| C | Mode Acak: batas waktu total = jumlah durasi per soal yang terpilih; tidak ada kunci per soal | Durasi per soal hanya bermakna sebagai timer penuh di Mode Serempak |
| D | Leaderboard live di Mode Acak hanya berisi peserta yang **sudah selesai** | Menampilkan skor peserta yang masih mengerjakan membocorkan benar atau salah ke yang menonton proyektor |
| E | "Tanpa batas" tetap dibatasi kapasitas server LAN | Batas riil hanya diketahui lewat uji beban di Fase 6 |
| F | Reset attempt oleh admin ada sebagai pengecualian untuk gangguan teknis | Bertabrakan dengan "tidak boleh mengulang". Hapus butir ini di Fase 6 jika tidak diinginkan. |

## Konteks Proyek (simpan sebagai AGENTS.md)

```
# Proyek: Website Kuis (LAN)

Tujuan: kuis online berbasis login di jaringan lokal. Peserta mengerjakan di PC masing-masing; PC proyektor menampilkan progres dan hasil dari server. Admin memilih mode per sesi: ACAK atau SEREMPAK.

Stack: Laravel (versi stabil terbaru), MySQL 8, Blade + Alpine.js + Tailwind (Vite), Laravel Reverb + Echo untuk realtime, database queue untuk job tertunda. Bahasa UI: Indonesia.

## Mode
- ACAK: tiap peserta mengerjakan sendiri. Soal dan opsi diacak per peserta. Batas waktu total = jumlah durasi_detik soal terpilih. Skor = jumlah_benar / jumlah_soal x 100, tampil di akhir.
- SEREMPAK: server membuka soal satu per satu untuk semua peserta. Urutan soal sama untuk semua, opsi diacak per peserta. Timer per soal (durasi_detik). Lanjut otomatis. Skor per soal = 0 jika salah; jika benar: round(1000 x (1 - (waktu_jawab_ms / (durasi_detik x 1000)) / 2)). Skor total = jumlah skor soal.

## Aturan keras (tidak boleh dilanggar)
1. JANGAN kirim is_correct atau jawaban benar ke peserta sebelum attempt selesai (Acak) atau soal ditutup (Serempak). Pakai API Resource terpisah untuk peserta.
2. Semua keputusan waktu memakai jam server (presisi milidetik). Jam browser hanya tampilan: server kirim server_now dan ends_at, klien menghitung selisih.
3. Satu attempt per (user, sesi): unique constraint di database.
4. Satu jawaban per soal: unique (attempt_id, question_id), pakai upsert; tolak jika lewat batas.
5. Satu sesi login aktif per user (users.current_session_token + middleware).
6. Pengacakan soal dan opsi dilakukan SEKALI saat attempt dibuat, disimpan di DB, tidak diacak ulang.
7. Otorisasi di server (middleware role). Peserta hanya boleh mengakses attempt miliknya.
8. Mode sesi dan kuis tidak bisa diubah setelah status bukan draft (validasi di server).
9. Jangan buat rate limit atau deteksi berbasis IP saja (PC lab bisa berbagi IP). Pakai user id.
10. Validasi lewat Form Request, query berparameter (Eloquent), CSRF aktif, password di-hash.
11. Kerjakan HANYA fase yang diminta. Jangan menambah fitur di luar fase.
12. Soal yang dipakai sesi yang sudah dibuka tidak boleh diedit atau dihapus.

## Skema database
users(id, nama, username UNIQUE, password, role[admin,peserta], current_session_token NULL, timestamps, deleted_at)
questions(id, teks, durasi_detik DEFAULT 30, pembahasan NULL, timestamps, deleted_at)
options(id, question_id, teks, is_correct)
quizzes(id, judul, jumlah_soal NULL, created_by)            -- NULL = semua soal di pool
quiz_questions(quiz_id, question_id)                         -- pool soal
quiz_sessions(id, quiz_id, nama, mode[acak,serempak], acak_opsi DEFAULT 1,
  tampilkan_pembahasan, leaderboard_live DEFAULT 1, max_peserta NULL, jeda_detik DEFAULT 5,
  status[draft,lobby,berjalan,selesai], fase NULL[soal_terbuka,jeda], soal_aktif_urutan NULL,
  soal_mulai_at NULL(ms), soal_berakhir_at NULL(ms), display_token UNIQUE, dibuka_at, ditutup_at, created_by)
session_questions(id, quiz_session_id, question_id, urutan)  -- urutan bersama, hanya Serempak
attempts(id, user_id, quiz_session_id, status[menunggu,berjalan,selesai], mulai_at NULL, batas_akhir_at NULL,
  selesai_at NULL, jumlah_benar DEFAULT 0, skor_total DEFAULT 0, UNIQUE(user_id, quiz_session_id))
attempt_questions(id, attempt_id, question_id, urutan, urutan_opsi_json)
answers(id, attempt_id, question_id, option_id NULL, waktu_jawab_ms NULL, skor DEFAULT 0, answered_at,
  UNIQUE(attempt_id, question_id))
attempt_events(id, attempt_id, tipe, detail NULL, ip, created_at)
```

## Fase 0: Setup dan login

```
Baca AGENTS.md. Kerjakan HANYA Fase 0.
- Buat proyek Laravel + MySQL + Tailwind + Alpine.js (Vite).
- Buat tabel users sesuai skema (username unik, role admin/peserta, current_session_token).
- Halaman login (username + password) dan logout. Seeder: 1 admin; password diambil dari .env, jangan di-hardcode.
- Middleware role (admin, peserta) dan middleware sesi tunggal: login baru merotasi current_session_token, sesi lama otomatis keluar pada request berikutnya.
- Layout dasar admin dan peserta, dashboard kosong, semua teks Bahasa Indonesia.

Kriteria penerimaan:
- Login di browser A lalu di browser B: browser A keluar saat refresh.
- Peserta membuka /admin/* mendapat 403.
- Password tersimpan sebagai hash.
- php artisan migrate:fresh --seed berjalan tanpa error.
```

## Fase 1: Manajemen peserta

```
Kerjakan HANYA Fase 1: manajemen peserta (khusus admin).
- CRUD peserta manual: nama, username, password (kosong = generate acak 8 karakter).
- Impor CSV (kolom: nama,username,password; password boleh kosong). Validasi per baris; username duplikat dilewati dan dilaporkan. Setelah impor tampilkan ringkasan (berhasil/gagal + alasan per baris) dan tombol unduh CSV berisi username dan password hasil generate (hanya tampil sekali).
- Reset password; hapus peserta (soft delete jika sudah punya attempt).
- Sediakan file contoh CSV untuk diunduh.

Kriteria penerimaan:
- Impor 100 baris berisi 3 username duplikat dan 2 baris rusak: 95 masuk, laporan menyebut nomor baris dan alasannya.
- Password hasil generate tidak tersimpan plaintext di database.
- Peserta tidak dapat membuka halaman ini.
```

## Fase 2: Bank soal, kuis, dan sesi

```
Kerjakan HANYA Fase 2.
- CRUD soal pilihan ganda: teks, 2-6 opsi, tepat satu is_correct, durasi_detik (default 30, rentang 5-300), pembahasan opsional.
- CRUD kuis: judul, pool soal (pilih banyak), jumlah_soal opsional (kosong = semua).
- CRUD sesi dari kuis: nama, mode (acak/serempak), acak_opsi (default true), tampilkan_pembahasan, leaderboard_live (default true), max_peserta (kosong = tanpa batas), jeda_detik (default 5, hanya relevan untuk serempak). Status awal draft.
- Tombol 'Buka lobby': status draft -> lobby, mode dan kuis terkunci, sistem membuat display_token acak panjang.
- Halaman admin sesi menampilkan URL display (/display/{token}) dengan tombol salin.

Kriteria penerimaan:
- Soal dengan 0 atau 2 jawaban benar ditolak.
- Sesi berstatus lobby: permintaan ubah mode ditolak oleh server (bukan hanya tombol disabled).
- Soal yang dipakai sesi yang sudah dibuka tidak bisa diedit atau dihapus.
```

## Fase 3: Mode Acak untuk peserta

```
Kerjakan HANYA Fase 3: Mode Acak.
- Dashboard peserta menampilkan sesi berstatus lobby/berjalan. Peserta menekan 'Gabung' (tolak bila max_peserta penuh); join membuat attempt berstatus menunggu.
- Admin menekan 'Mulai' (lobby -> berjalan) untuk sesi mode acak. Peserta yang sudah join menekan 'Mulai mengerjakan': server membuat attempt_questions (pilih jumlah_soal acak dari pool, acak urutan soal, acak urutan opsi jika acak_opsi), set mulai_at, batas_akhir_at = mulai_at + jumlah durasi_detik soal terpilih, status berjalan. Refresh atau buka ulang melanjutkan attempt yang sama dengan urutan yang sama.
- Halaman soal: satu soal per layar, navigasi nomor soal, penanda terjawab, hitung mundur dari server (kirim server_now dan batas_akhir_at).
- Autosave: POST jawaban setiap klik (upsert); tolak 403 bila now() > batas_akhir_at atau attempt sudah selesai.
- Selesai: tombol Selesai atau waktu habis -> server mengunci attempt, hitung skor, tampilkan nilai. Jika tampilkan_pembahasan aktif tampilkan jawaban benar dan pembahasan; jika tidak, hanya nilai.
- Command terjadwal tiap menit menutup attempt yang melewati batas_akhir_at dan menghitung skor dari jawaban tersimpan.
- Tombol admin 'Tutup sesi' menutup semua attempt yang berjalan.
- API peserta TIDAK mengembalikan is_correct sebelum attempt selesai.
- Tidak ada tombol atau jalur untuk mengulang attempt.

Kriteria penerimaan:
- Dua peserta mendapat urutan soal dan opsi berbeda.
- Refresh tidak mengubah urutan.
- Respons JSON soal tidak memuat is_correct (cek tab Network).
- POST jawaban setelah batas_akhir_at mengembalikan 403 dan jawaban tidak tersimpan.
- Peserta menutup browser sebelum selesai: setelah batas waktu, scheduler menutup attempt dan nilai terhitung dari jawaban tersimpan.
- Memulai kuis yang sama untuk kedua kalinya tidak membuat attempt baru.
```

## Fase 4: Realtime dan display proyektor

```
Kerjakan HANYA Fase 4: infrastruktur realtime dan halaman display.
- Pasang Laravel Reverb + Echo. Konfigurasi host untuk LAN (REVERB_HOST = IP LAN server, bukan localhost) dan dokumentasikan di README.
- Halaman /display/{token}: tanpa login, token acak panjang, 404 bila salah. Read-only, tampilan layar besar, Alpine + Echo, channel display.{token}.
- Fase lobby: daftar peserta yang bergabung dan jumlahnya.
- Fase berjalan (Mode Acak): jumlah selesai/total dan rata-rata soal terjawab. JANGAN tampilkan jawaban benar atau skor peserta yang belum selesai.
- Leaderboard live (jika leaderboard_live aktif): 5 teratas dari peserta yang SUDAH selesai (nama + skor).
- Setelah sesi ditutup: leaderboard lengkap, distribusi nilai (histogram), 5 soal dengan persentase benar terendah.
- Broadcast event saat jawaban tersimpan (throttle maksimal 1 pembaruan progres per detik) dan saat attempt selesai.
- Fallback: bila WebSocket putus, display polling /display/{token}/state tiap 3 detik.

Kriteria penerimaan:
- Display terbuka tanpa login; token salah menghasilkan 404.
- Saat peserta mengerjakan, angka selesai/total di proyektor ter-update kurang dari 2 detik tanpa refresh.
- Payload event display tidak memuat option_id, is_correct, atau skor peserta yang masih berjalan (periksa frame WebSocket).
- Reverb dimatikan: display tetap ter-update lewat polling.
- Dari PC lain di LAN, display terbuka lewat http://IP-SERVER.
```

## Fase 5: Mode Serempak

```
Kerjakan HANYA Fase 5: Mode Serempak (siklus game dikendalikan server).
Status sesi: lobby -> berjalan -> selesai. Fase saat berjalan: soal_terbuka atau jeda. Gunakan kolom soal_aktif_urutan, soal_mulai_at, soal_berakhir_at.

- Saat admin menekan 'Mulai': server memilih jumlah_soal dari pool, mengacak urutan SEKALI (session_questions), membuat attempt_questions untuk setiap peserta yang sudah join dengan urutan soal yang sama dan urutan opsi acak per peserta (jika acak_opsi). Langsung masuk soal 1.
- Siklus per soal: fase soal_terbuka; soal_mulai_at = now(); soal_berakhir_at = soal_mulai_at + durasi_detik soal itu. Broadcast SoalDibuka {urutan, total, server_now, ends_at} ke channel private session.{id}. Klien lalu GET soal aktif miliknya (opsi sesuai urutan acaknya, tanpa is_correct).
- Soal ditutup bila (a) waktu habis, atau (b) semua attempt di sesi sudah menjawab. Penutupan dijalankan oleh queued job tertunda (database queue) yang IDEMPOTENT: cek soal_aktif_urutan dan fase sebelum bertindak, sehingga eksekusi ganda tidak menghitung skor dua kali. Tombol admin 'Tutup/lewati soal' memanggil logika yang sama.
- Saat ditutup: hitung skor semua jawaban soal itu dengan rumus di AGENTS.md; waktu_jawab_ms = waktu server saat jawaban diterima dikurangi soal_mulai_at. Masuk fase jeda selama jeda_detik: broadcast SoalDitutup {jawaban_benar, distribusi, top5} ke peserta dan display; peserta melihat benar/salah dan skornya sendiri.
- Setelah jeda, otomatis lanjut ke soal berikutnya. Setelah soal terakhir: status selesai, tampilkan hasil akhir.
- Jawaban: satu per peserta per soal; ditolak bila fase bukan soal_terbuka atau now() > soal_berakhir_at.
- Reconnect: peserta yang refresh atau terputus langsung mendapat state sesi saat ini (soal aktif + sisa waktu, atau jeda) lewat GET /sesi/{id}/state.
- Display: 'Soal ke-N dari M', jumlah yang sudah menjawab, hitung mundur. Saat jeda: distribusi jawaban, jawaban benar, 5 teratas. Akhir: leaderboard lengkap.
- Hormati max_peserta, mode terkunci, dan satu attempt per user.

Kriteria penerimaan:
- 3 peserta + 1 display: soal berpindah otomatis sampai selesai tanpa klik admin.
- Soal muncul di semua peserta dengan selisih kurang dari 1 detik di LAN.
- Jawaban sebelum waktu habis diterima; sesudahnya mengembalikan 403.
- Semua peserta menjawab lebih awal: soal menutup lebih cepat dari timer.
- Payload SoalDibuka dan GET soal aktif tidak memuat is_correct; jawaban benar baru ada di SoalDitutup.
- Peserta refresh di tengah soal: kembali ke soal yang sama dengan sisa waktu benar.
- Restart queue worker saat jeda tidak menggandakan skor.
- Jawaban benar di detik awal bernilai lebih tinggi dari jawaban benar di detik akhir.
```

## Fase 6: Anti-curang, laporan, dan penyiapan LAN

```
Kerjakan HANYA Fase 6.
- Log aktivitas: klien mengirim event (blur_window, tab_hidden, fullscreen_exit, copy_attempt) ke POST /attempt/{id}/events, throttle per user (bukan IP). Simpan di attempt_events. Tampilkan peringatan ke peserta; JANGAN diskualifikasi otomatis.
- Mode fullscreen dianjurkan saat mengerjakan (tombol, bukan paksaan).
- Admin: log per peserta, penanda 'perlu ditinjau' bila 3 kejadian atau lebih, filter.
- Laporan sesi: tabel nilai, detail jawaban per peserta, persentase benar per soal, ekspor CSV.
- Admin dapat mereset attempt satu peserta (hapus attempt dan jawabannya) dengan konfirmasi dan catatan di log; hanya untuk gangguan teknis.
- Hardening: indeks (answers unique attempt_id+question_id; attempts quiz_session_id+status), cek N+1, rate limit login per username.
- Skrip uji beban k6 dengan 100 peserta virtual: login, join, jawab. Angka 100 hanya titik awal; sesuaikan dengan target kelas.
- README deploy LAN: Nginx + PHP-FPM + MySQL, proses reverb:start dan queue:work di bawah Supervisor atau systemd, scheduler via cron, cara akses lewat IP LAN.

Kriteria penerimaan:
- Pindah tab menambah log; tidak ada diskualifikasi otomatis.
- CSV hasil terbuka di Excel dengan kolom benar.
- Uji k6 tanpa error 5xx; catat waktu respons autosave dan putuskan sendiri ambang yang dapat diterima setelah diukur di perangkat server asli.
- README dapat diikuti dari server kosong.
```

## Risiko yang tetap ada

- Anti-curang tidak bisa 100%: perangkat kedua dan memotret layar tidak terdeteksi. Fase 6 hanya membuat kecurangan mahal dan terlihat.
- Mode Serempak lebih mudah dicontek karena soal sama untuk semua. Untuk kuis yang nilainya penting, pakai Mode Acak.
- Hasil vibe coding perlu ditinjau manusia, terutama aturan keras nomor 1, 2, dan 4. Cek manual di tab Network, bukan hanya percaya laporan AI.
- Versi Laravel Reverb dan caranya dikonfigurasi di LAN perlu dicek di dokumentasi resmi saat Fase 4.
