# Owner Cleanup and Latest Scores Design

## Goal

Master dapat menghapus akun owner yang benar-benar yatim, dan widget **Nilai Terbaru** menampilkan lima attempt terbaru dari skor tertinggi ke terendah.

## Owner deletion

Akun layak dihapus hanya jika seluruh syarat berikut terpenuhi:

- `role` adalah `admin` dan bukan platform admin.
- Tidak memiliki workspace yang dikelola (`ownedOrganizations` kosong).
- Tidak terdaftar sebagai anggota workspace mana pun (`organizations` kosong, termasuk status nonaktif).
- Tidak memiliki `WorkspaceRequest` berstatus `pending`.

Perilaku server:

- Route: `DELETE /master/owners/{owner}` dengan nama `master.owners.destroy`.
- Akun yang tidak memenuhi syarat identitas mendapat `404`.
- Akun yang masih memiliki workspace, keanggotaan, atau permintaan pending mendapat `422`.
- Akun di-soft-delete, `current_session_token` dikosongkan, lalu dicatat sebagai `owner_deleted` di `audit_logs`.
- Respons sukses kembali ke daftar owner dengan pesan sukses.

Perilaku UI:

- Daftar owner menampilkan kolom aksi.
- Tombol **Hapus akun** hanya muncul untuk akun yang memenuhi seluruh syarat.
- Akun tanpa workspace tetapi masih anggota workspace menampilkan **Masih anggota workspace**.
- Akun tanpa workspace tetapi memiliki permintaan pending menampilkan **Permintaan pending**.
- Konfirmasi browser wajib sebelum menghapus.

## Latest scores

- Pertahankan semantik widget: ambil lima attempt terbaru berdasarkan `updated_at`, bukan top five sepanjang masa.
- Setelah lima attempt diambil, urutkan koleksi berdasarkan `skor_total` menurun.
- Nilai yang ditampilkan tetap `skor_total`, sama dengan label **Skor** di widget.
- Attempt paling lama dengan skor tinggi tidak boleh masuk jika bukan bagian dari lima terbaru.

## Non-goals

- Tidak mengubah transfer owner.
- Tidak menghapus workspace, attempt, atau riwayat audit milik akun yang dihapus.
- Tidak mengubah label atau struktur visual widget Nilai Terbaru.

## Verification

- Feature test untuk hapus owner yatim, penolakan owner dengan workspace, penolakan anggota workspace, penolakan permintaan pending, soft-delete, dan audit log.
- Feature test untuk tombol hapus dan alasan tidak dapat dihapus di daftar owner.
- Feature test untuk urutan lima attempt terbaru berdasarkan `skor_total`.
- Jalankan test suite, `php artisan view:cache`, dan detector Impeccable pada dua view yang berubah.
