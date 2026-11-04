import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

const loginDuration = new Trend('login_duration', true);
const dashboardDuration = new Trend('dashboard_duration', true);
const stateDuration = new Trend('state_duration', true);

const VUS = Number(__ENV.VUS || 100);
const PREFIX = __ENV.USERNAME_PREFIX || 'peserta';
const PASSWORD = __ENV.PASSWORD || 'password';
const SESSION_ID = __ENV.SESSION_ID || '';
const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/+$/, '');

export const options = {
    scenarios: {
        masuk_serentak: {
            executor: 'per-vu-iterations',
            vus: VUS,
            iterations: 1,
            maxDuration: '3m',
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        login_duration: ['p(95)<2000'],
        dashboard_duration: ['p(95)<2000'],
    },
};

export function setup() {
    const response = http.get(`${BASE_URL}/login`);
    check(response, {
        'halaman login dapat dibuka': (r) => r.status === 200 && r.body.includes('name="_token"'),
    });
    return { baseUrl: BASE_URL };
}

export default function (data) {
    const username = `${PREFIX}${__VU}`;

    const loginPage = http.get(`${data.baseUrl}/login`, {
        tags: { halaman: 'login' },
    });

    const body = loginPage.body || '';
    const tokenMatch =
        body.match(/name="_token"\s+value="([^"]+)"/) ||
        body.match(/value="([^"]+)"\s+name="_token"/);

    const token = tokenMatch ? tokenMatch[1] : '';

    const login = http.post(
        `${data.baseUrl}/login`,
        {
            _token: token,
            username,
            password: PASSWORD,
        },
        {
            redirects: 0,
            tags: { aksi: 'login' },
        }
    );

    loginDuration.add(login.timings.duration);

    check(login, {
        'login diterima': (r) => r.status === 302,
        'diarahkan ke dashboard': (r) => (r.headers.Location || '').includes('/dashboard'),
    });

    if (login.status !== 302) {
        return;
    }

    const dashboard = http.get(`${data.baseUrl}/dashboard`, {
        tags: { halaman: 'dashboard' },
    });

    dashboardDuration.add(dashboard.timings.duration);

    check(dashboard, {
        'dashboard peserta tampil': (r) => r.status === 200,
    });

    if (SESSION_ID !== '') {
        const state = http.get(`${data.baseUrl}/sesi/${SESSION_ID}/state`, {
            headers: { Accept: 'application/json' },
            tags: { aksi: 'state' },
        });

        stateDuration.add(state.timings.duration);

        check(state, {
            'state sesi terjangkau': (r) => r.status === 200 || r.status === 404,
        });
    }

    sleep(1);
}
