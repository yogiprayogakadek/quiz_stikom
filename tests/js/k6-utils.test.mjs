import assert from 'node:assert/strict';
import test from 'node:test';

import { redirectPath, redirectsTo, workspaceLoginUrl } from '../load/k6-utils.js';

test('redirect k6 menerima URL absolut dan relatif tanpa mengabaikan path lain', () => {
    assert.equal(redirectPath('https://quiz.example/ganti-password?first=1'), '/ganti-password');
    assert.equal(redirectPath('/sesi/12/kerjakan'), '/sesi/12/kerjakan');
    assert.equal(redirectsTo('https://quiz.example/dashboard', '/dashboard'), true);
    assert.equal(redirectsTo('/dashboard-lain', '/dashboard'), false);
});

test('URL login workspace dibentuk dari origin dan slug yang tervalidasi', () => {
    assert.equal(
        workspaceLoginUrl('https://quiz.ruangproject.my.id/', 'simulasi-kubernetes'),
        'https://quiz.ruangproject.my.id/o/simulasi-kubernetes/login'
    );
    assert.throws(
        () => workspaceLoginUrl('https://quiz.ruangproject.my.id/o/simulasi-kubernetes/login', 'simulasi-kubernetes'),
        /BASE_URL harus berupa origin tanpa path/
    );
    assert.throws(
        () => workspaceLoginUrl('https://quiz.ruangproject.my.id', ''),
        /WORKSPACE_SLUG wajib diisi/
    );
    assert.throws(
        () => workspaceLoginUrl('https://quiz.ruangproject.my.id', 'simulasi/kubernetes'),
        /WORKSPACE_SLUG wajib diisi/
    );
});
