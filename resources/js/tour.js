import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

const terlihat = (el) => el instanceof HTMLElement && el.getClientRects().length > 0;
const cari = (selector) => [...document.querySelectorAll(selector)].find(terlihat) ?? null;

let aktif = false;

function tandaiSelesai() {
    if (!window.pesertaTourSelesaiUrl) return;

    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    fetch(window.pesertaTourSelesaiUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: '{}',
        credentials: 'same-origin',
        keepalive: true,
    }).catch(() => {});
}

window.mulaiTourPeserta = () => {
    if (aktif) return;

    const langkah = [];

    langkah.push({
        popover: {
            title: 'Selamat datang',
            description: 'Tur singkat ini menunjukkan menu, daftar sesi, dan cara mengerjakan kuis. Anda bisa menutupnya kapan saja.',
        },
    });

    const sidebar = cari('#sidebar-peserta');
    if (sidebar) {
        langkah.push({
            element: sidebar,
            popover: {
                title: 'Menu peserta',
                description: 'Beranda menampilkan sesi aktif. Kelas Saya berisi kelas yang sudah Anda ikuti. Panduan membuka tur ini lagi.',
                side: 'right',
                align: 'start',
            },
        });
    } else {
        const menu = cari('[data-tour="menu-utama"]');
        if (menu) {
            langkah.push({
                element: menu,
                popover: {
                    title: 'Menu peserta',
                    description: 'Buka menu ini untuk berpindah antara Beranda, Kelas Saya, dan Panduan.',
                    side: 'bottom',
                    align: 'start',
                },
            });
        }
    }

    const pencarian = cari('[data-tour="cari"]');
    if (pencarian) {
        langkah.push({
            element: pencarian,
            popover: {
                title: 'Cari cepat',
                description: 'Ketik nama kuis, sesi, atau kelas untuk menyaring daftar di halaman ini.',
                side: 'bottom',
                align: 'start',
            },
        });
    }

    const hero = cari('[data-tour="hero"]');
    if (hero) {
        langkah.push({
            element: hero,
            popover: {
                title: 'Mulai dari sini',
                description: 'Tombol Mulai Kuis mengarah ke daftar sesi. Tombol Panduan membuka tur ini lagi kapan pun.',
                side: 'bottom',
                align: 'start',
            },
        });
    }

    const sesi = cari('#sesi-tersedia-heading');
    if (sesi) {
        langkah.push({
            element: sesi,
            popover: {
                title: 'Sesi yang dibuka',
                description: 'Saat admin membuka sesi, kartu sesi muncul di sini. Kuis Uji Coba berisi 3 soal latihan untuk mencoba alur ujian.',
                side: 'top',
                align: 'start',
            },
        });
    }

    const kelas = cari('#kelas-saya-heading');
    if (kelas) {
        langkah.push({
            element: kelas,
            popover: {
                title: 'Kelas saya',
                description: 'Masuk ke kelas dengan PIN dari admin. Kuis dan sesi untuk kelas tersebut akan tampil di Beranda.',
                side: 'top',
                align: 'start',
            },
        });
    }

    const peringkat = cari('[data-tour="leaderboard"]');
    if (peringkat) {
        langkah.push({
            element: peringkat,
            popover: {
                title: 'Peringkat kelas',
                description: 'Skor dari kuis yang sudah selesai dijumlahkan di sini.',
                side: 'left',
                align: 'start',
            },
        });
    }

    langkah.push({
        popover: {
            title: 'Cara mengerjakan kuis',
            description: '1) Tekan Gabung sesi. 2) Tekan Mulai Ujian. 3) Aktifkan layar penuh saat diminta. 4) Pilih jawaban, lalu tunggu sampai tersimpan. 5) Tekan Selesai setelah semua soal terjawab.',
        },
    });

    langkah.push({
        popover: {
            title: 'Siap mencoba?',
            description: 'Mulai dari Kuis Uji Coba 3 soal. Jika ingin melihat tur ini lagi, buka menu Panduan.',
        },
    });

    aktif = true;

    driver({
        steps: langkah,
        showProgress: true,
        progressText: '{{current}} dari {{total}}',
        nextBtnText: 'Lanjut',
        prevBtnText: 'Kembali',
        doneBtnText: 'Selesai',
        allowClose: true,
        overlayOpacity: 0.6,
        stagePadding: 8,
        stageRadius: 14,
        smoothScroll: true,
        popoverClass: 'tour-popover',
        onDestroyed: () => {
            aktif = false;
            tandaiSelesai();
        },
    }).drive();
};
