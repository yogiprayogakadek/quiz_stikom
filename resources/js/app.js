import Alpine from 'alpinejs';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

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

Alpine.start();
