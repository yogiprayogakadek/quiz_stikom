export function redirectPath(location) {
    const value = String(location || '').replace(/^[a-z][a-z0-9+.-]*:\/\/[^/]+/i, '');

    return value.split(/[?#]/, 1)[0] || '/';
}

export function redirectsTo(location, path) {
    return redirectPath(location) === path;
}

export function workspaceLoginUrl(baseUrl, workspaceSlug) {
    const origin = String(baseUrl || '').replace(/\/+$/, '');
    if (!/^https?:\/\/[^/?#]+$/i.test(origin)) {
        throw new Error('BASE_URL harus berupa origin tanpa path, misalnya https://quiz.example.com.');
    }
    if (!/^[A-Za-z0-9_-]{1,80}$/.test(String(workspaceSlug || ''))) {
        throw new Error('WORKSPACE_SLUG wajib diisi dan hanya boleh memuat huruf, angka, tanda hubung, atau garis bawah.');
    }

    return `${origin}/o/${workspaceSlug}/login`;
}
