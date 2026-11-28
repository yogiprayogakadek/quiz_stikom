import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import { Rate, Trend } from 'k6/metrics';
import { redirectsTo, workspaceLoginUrl } from './k6-utils.js';

const loginDuration = new Trend('login_duration', true);
const passwordChangeDuration = new Trend('password_change_duration', true);
const joinDuration = new Trend('join_duration', true);
const dashboardDuration = new Trend('dashboard_duration', true);
const stateDuration = new Trend('state_duration', true);
const answerDuration = new Trend('answer_duration', true);
const workflowFailures = new Rate('workflow_failures');

const VUS = Number(__ENV.VUS || 100);
const PREFIX = __ENV.USERNAME_PREFIX || 'loadtest';
const PASSWORD = __ENV.PASSWORD || '';
const SESSION_ID = __ENV.SESSION_ID || '';
const WORKSPACE_SLUG = __ENV.WORKSPACE_SLUG || '';
const DISPLAY_TOKEN = __ENV.DISPLAY_TOKEN || '';
const DURATION_SECONDS = Number(__ENV.DURATION_SECONDS || 600);
const TEST_DURATION = `${DURATION_SECONDS}s`;
const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/+$/, '');
const LOGIN_URL = workspaceLoginUrl(BASE_URL, WORKSPACE_SLUG);

const scenarios = {
    peserta: {
        executor: 'per-vu-iterations',
        exec: 'peserta',
        vus: VUS,
        iterations: 1,
        maxDuration: `${DURATION_SECONDS + 120}s`,
    },
};

if (DISPLAY_TOKEN !== '') {
    scenarios.display = {
        executor: 'constant-vus',
        exec: 'display',
        vus: 1,
        duration: TEST_DURATION,
    };
}

if (__ENV.ADMIN_USERNAME && SESSION_ID !== '') {
    scenarios.monitor_admin = {
        executor: 'per-vu-iterations',
        exec: 'monitorAdmin',
        vus: 1,
        iterations: 1,
        maxDuration: `${DURATION_SECONDS + 120}s`,
    };
}

export const options = {
    scenarios,
    thresholds: {
        http_req_failed: ['rate<0.01'],
        workflow_failures: ['rate<0.01'],
        state_duration: ['p(95)<500'],
        answer_duration: ['p(95)<750'],
    },
};

function csrfToken(body) {
    const html = body || '';
    const match =
        html.match(/name="_token"\s+value="([^"]+)"/) ||
        html.match(/value="([^"]+)"\s+name="_token"/);

    return match ? match[1] : '';
}

function record(condition, label) {
    workflowFailures.add(!condition);
    check(condition, { [label]: (passed) => passed });

    return condition;
}

function login(username, password = PASSWORD, completeFirstLogin = false) {
    const page = http.get(LOGIN_URL, { tags: { halaman: 'login_workspace' } });
    const token = csrfToken(page.body);
    if (!record(page.status === 200 && token !== '', 'halaman login dan token CSRF tersedia')) return '';

    const response = http.post(
        LOGIN_URL,
        { _token: token, username, password },
        { redirects: 0, tags: { aksi: 'login' } }
    );

    loginDuration.add(response.timings.duration);
    const location = response.headers.Location || '';
    const loginAccepted = response.status === 302;
    if (!record(loginAccepted, 'login diterima')) return '';

    if (redirectsTo(location, '/ganti-password')) {
        if (!completeFirstLogin) {
            record(false, 'akun monitor tidak meminta ganti password');

            return '';
        }

        const change = http.post(
            `${BASE_URL}/ganti-password`,
            {
                _token: token,
                password,
                password_confirmation: password,
            },
            { redirects: 0, tags: { aksi: 'ganti_password_pertama' } }
        );
        passwordChangeDuration.add(change.timings.duration);
        const changed = change.status === 302 && redirectsTo(change.headers.Location || '', '/dashboard');
        if (!record(changed, 'password pertama diselesaikan')) return '';
    } else {
        const expectedPath = username === __ENV.ADMIN_USERNAME ? '/admin' : '/dashboard';
        if (!record(redirectsTo(location, expectedPath), 'login diarahkan ke area akun')) return '';
    }

    return token;
}

function jsonPost(url, token, payload, tags) {
    return http.post(url, JSON.stringify(payload), {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
        },
        tags,
    });
}

export function setup() {
    if (SESSION_ID === '') throw new Error('SESSION_ID wajib diisi.');
    if (PASSWORD.length < 8) throw new Error('PASSWORD peserta minimal 8 karakter.');
    if (__ENV.ADMIN_USERNAME && !__ENV.ADMIN_PASSWORD) {
        throw new Error('ADMIN_PASSWORD wajib diisi ketika ADMIN_USERNAME digunakan.');
    }

    const response = http.get(LOGIN_URL);
    const tokenAvailable = (response.body || '').includes('name="_token"');
    const available = response.status === 200 && tokenAvailable;
    if (!record(available, 'halaman login dapat dibuka')) {
        throw new Error(`Halaman login workspace tidak dapat digunakan: ${LOGIN_URL} (status ${response.status}, token CSRF ${tokenAvailable ? 'tersedia' : 'tidak tersedia'}).`);
    }
}

export function peserta() {
    const participantNumber = exec.scenario.iterationInTest + 1;
    const token = login(`${PREFIX}${participantNumber}`, PASSWORD, true);
    if (token === '') return;

    const dashboard = http.get(`${BASE_URL}/dashboard`, { tags: { halaman: 'dashboard' } });
    dashboardDuration.add(dashboard.timings.duration);
    if (!record(dashboard.status === 200, 'dashboard peserta tampil')) return;

    const join = http.post(
        `${BASE_URL}/sesi/${SESSION_ID}/gabung`,
        { _token: token },
        { redirects: 0, tags: { aksi: 'gabung_simulasi' } }
    );
    joinDuration.add(join.timings.duration);
    const joined = join.status === 302
        && redirectsTo(join.headers.Location || '', `/sesi/${SESSION_ID}/kerjakan`);
    if (!record(joined, 'peserta bergabung ke simulasi')) return;

    const endsAt = Date.now() + DURATION_SECONDS * 1000;
    let lastQuestionId = null;

    while (Date.now() < endsAt) {
        if (SESSION_ID !== '') {
            const state = http.get(`${BASE_URL}/sesi/${SESSION_ID}/state`, {
                headers: { Accept: 'application/json' },
                tags: { aksi: 'state_serempak' },
            });
            stateDuration.add(state.timings.duration);
            const stateAvailable = state.status === 200;
            record(stateAvailable, 'state sesi terjangkau');

            if (stateAvailable) {
                const data = state.json();
                const question = data.soal;
                const option = question?.opsi?.[0];
                if (question && option && question.id !== lastQuestionId) {
                    const answer = jsonPost(
                        `${BASE_URL}/sesi/${SESSION_ID}/jawab`,
                        token,
                        { question_id: question.id, option_id: option.id },
                        { aksi: 'jawab_pg' }
                    );
                    answerDuration.add(answer.timings.duration);
                    const saved = answer.status === 200;
                    record(saved, 'jawaban PG tersimpan');
                    if (saved) lastQuestionId = question.id;
                }
            }
        }

        sleep(5 + Math.random());
    }
}

export function display() {
    const response = http.get(`${BASE_URL}/display/${DISPLAY_TOKEN}/state`, {
        headers: { Accept: 'application/json' },
        tags: { aksi: 'state_display' },
    });
    stateDuration.add(response.timings.duration);
    record(response.status === 200, 'display terjangkau');
    sleep(5 + Math.random());
}

export function monitorAdmin() {
    const token = login(__ENV.ADMIN_USERNAME, __ENV.ADMIN_PASSWORD || PASSWORD);
    if (token === '') return;

    const endsAt = Date.now() + DURATION_SECONDS * 1000;
    while (Date.now() < endsAt) {
        const response = http.get(`${BASE_URL}/admin/sessions/${SESSION_ID}/monitor`, {
            headers: { Accept: 'application/json' },
            tags: { aksi: 'monitor_admin' },
        });
        stateDuration.add(response.timings.duration);
        record(response.status === 200, 'monitor admin terjangkau');
        sleep(5 + Math.random());
    }
}
