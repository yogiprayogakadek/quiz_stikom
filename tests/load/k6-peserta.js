import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import { Trend } from 'k6/metrics';

const loginDuration = new Trend('login_duration', true);
const dashboardDuration = new Trend('dashboard_duration', true);
const stateDuration = new Trend('state_duration', true);
const answerDuration = new Trend('answer_duration', true);

const VUS = Number(__ENV.VUS || 100);
const PREFIX = __ENV.USERNAME_PREFIX || 'peserta';
const PASSWORD = __ENV.PASSWORD || 'password';
const SESSION_ID = __ENV.SESSION_ID || '';
const DISPLAY_TOKEN = __ENV.DISPLAY_TOKEN || '';
const ATTEMPT_ID_BASE = Number(__ENV.ATTEMPT_ID_BASE || 0);
const ESSAY_QUESTION_ID = Number(__ENV.ESSAY_QUESTION_ID || 0);
const DURATION_SECONDS = Number(__ENV.DURATION_SECONDS || 600);
const TEST_DURATION = `${DURATION_SECONDS}s`;
const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/+$/, '');

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

function login(username, password = PASSWORD) {
    const page = http.get(`${BASE_URL}/login`, { tags: { halaman: 'login' } });
    const token = csrfToken(page.body);
    const response = http.post(
        `${BASE_URL}/login`,
        { _token: token, username, password },
        { redirects: 0, tags: { aksi: 'login' } }
    );

    loginDuration.add(response.timings.duration);
    check(response, {
        'login diterima': (r) => r.status === 302,
        'login diarahkan ke area akun': (r) => {
            const location = r.headers.Location || '';
            return location.includes('/dashboard') || location.includes('/admin');
        },
    });

    return response.status === 302 ? token : '';
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
    const response = http.get(`${BASE_URL}/login`);
    check(response, {
        'halaman login dapat dibuka': (r) => r.status === 200 && r.body.includes('name="_token"'),
    });
}

export function peserta() {
    const participantNumber = exec.scenario.iterationInTest + 1;
    const token = login(`${PREFIX}${participantNumber}`);
    if (token === '') return;

    const dashboard = http.get(`${BASE_URL}/dashboard`, { tags: { halaman: 'dashboard' } });
    dashboardDuration.add(dashboard.timings.duration);
    check(dashboard, { 'dashboard peserta tampil': (r) => r.status === 200 });

    const endsAt = Date.now() + DURATION_SECONDS * 1000;
    let lastQuestionId = null;

    while (Date.now() < endsAt) {
        if (SESSION_ID !== '') {
            const state = http.get(`${BASE_URL}/sesi/${SESSION_ID}/state`, {
                headers: { Accept: 'application/json' },
                tags: { aksi: 'state_serempak' },
            });
            stateDuration.add(state.timings.duration);
            check(state, { 'state sesi terjangkau': (r) => r.status === 200 || r.status === 404 });

            if (state.status === 200) {
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
                    check(answer, { 'jawaban PG tersimpan': (r) => r.status === 200 || r.status === 403 });
                    lastQuestionId = question.id;
                }
            }
        }

        if (ATTEMPT_ID_BASE > 0 && ESSAY_QUESTION_ID > 0) {
            const attemptId = ATTEMPT_ID_BASE + participantNumber - 1;
            const answer = jsonPost(
                `${BASE_URL}/attempt/${attemptId}/jawab`,
                token,
                {
                    question_id: ESSAY_QUESTION_ID,
                    jawaban_teks: `Jawaban uji beban peserta ${participantNumber} pada ${Date.now()}`,
                },
                { aksi: 'jawab_essay' }
            );
            answerDuration.add(answer.timings.duration);
            check(answer, { 'jawaban essay tersimpan': (r) => r.status === 200 || r.status === 403 });
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
    check(response, { 'display terjangkau': (r) => r.status === 200 });
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
        check(response, { 'monitor admin terjangkau': (r) => r.status === 200 });
        sleep(5 + Math.random());
    }
}
