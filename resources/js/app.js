import Alpine from 'alpinejs';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import './tour';

window.Pusher = Pusher;
window.Alpine = Alpine;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

window.kuisFetch = async (url, options = {}) => {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
        ...(options.headers ?? {}),
    };

    let body = options.body;

    if (body !== undefined && body !== null && typeof body !== 'string') {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(body);
    }

    const response = await fetch(url, {
        ...options,
        headers,
        body,
        credentials: 'same-origin',
    });

    let data = null;

    try {
        data = await response.json();
    } catch {
        data = null;
    }

    return { ok: response.ok, status: response.status, data };
};

let echoInstance = null;

window.buatEcho = () => {
    if (echoInstance !== null) {
        return echoInstance;
    }

    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (!key) {
        return null;
    }

    let wsHost = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
    const lokal = ['localhost', '127.0.0.1'];

    if (lokal.includes(wsHost) && !lokal.includes(window.location.hostname)) {
        wsHost = window.location.hostname;
    }

    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
    const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: scheme === 'https' ? ['wss'] : ['ws'],
    });

    window.Echo = echoInstance;

    return echoInstance;
};

window.salinTeks = async (teks, tombol) => {
    try {
        await navigator.clipboard.writeText(teks);
    } catch {
        const area = document.createElement('textarea');
        area.value = teks;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    }

    if (tombol) {
        const isiAsli = tombol.innerHTML;
        tombol.textContent = 'Tersalin';
        setTimeout(() => {
            tombol.innerHTML = isiAsli;
        }, 1600);
    }
};

window.tabelData = (config = {}) => ({
    cari: '',
    perPage: config.perPage || 10,
    halaman: 1,
    totalFiltered: 0,
    totalHalaman: 1,
    dari: 0,
    sampai: 0,
    daftarHalaman: [],
    items: [],

    init() {
        this.$nextTick(() => {
            this.muatItem();
            this.hitung();
            this.$watch('cari', () => {
                this.halaman = 1;
                this.hitung();
            });
            this.$watch('perPage', () => {
                this.halaman = 1;
                this.hitung();
            });
        });
    },

    muatItem() {
        // shortcut: DOM scan client-side up to 2000 rows, upgrade to server AJAX API if dataset is huge
        const els = this.$el.querySelectorAll('[data-item]');
        this.items = Array.from(els).map((el, i) => ({
            id: i,
            el: el,
            text: (el.getAttribute('data-search') || el.textContent || '').toLowerCase().replace(/\s+/g, ' ')
        }));
    },

    hitung() {
        if (!this.items.length) {
            this.muatItem();
        }

        const q = this.cari.trim().toLowerCase();
        const filtered = q === '' 
            ? this.items 
            : this.items.filter(item => item.text.includes(q));

        this.totalFiltered = filtered.length;
        this.totalHalaman = Math.max(1, Math.ceil(this.totalFiltered / this.perPage));
        if (this.halaman > this.totalHalaman) {
            this.halaman = this.totalHalaman;
        }

        const start = (this.halaman - 1) * this.perPage;
        const end = start + this.perPage;
        const visibleIds = new Set(filtered.slice(start, end).map(item => item.id));

        this.items.forEach(item => {
            item.el.style.display = visibleIds.has(item.id) ? '' : 'none';
        });

        this.dari = this.totalFiltered === 0 ? 0 : start + 1;
        this.sampai = Math.min(end, this.totalFiltered);

        const delta = 2;
        const range = [];
        for (let i = Math.max(1, this.halaman - delta); i <= Math.min(this.totalHalaman, this.halaman + delta); i++) {
            range.push(i);
        }
        this.daftarHalaman = range;
    },

    keHalaman(p) {
        if (p >= 1 && p <= this.totalHalaman) {
            this.halaman = p;
            this.hitung();
        }
    },

    halamanSebelumnya() {
        if (this.halaman > 1) {
            this.halaman--;
            this.hitung();
        }
    },

    halamanBerikutnya() {
        if (this.halaman < this.totalHalaman) {
            this.halaman++;
            this.hitung();
        }
    }
});

Alpine.start();
