const FALLBACK_SLUG = 'workspace';
const FALLBACK_TIMEZONE = 'Asia/Jakarta';

const slugify = (value) =>
    value
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9_-]+/g, '-')
        .replace(/-{2,}/g, '-')
        .replace(/^[-_]+|[-_]+$/g, '')
        .slice(0, 80);

const cleanSlug = (value) =>
    value
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9_-]+/g, '-')
        .replace(/-{2,}/g, '-')
        .slice(0, 80);

const initRegistration = () => {
    const root = document.querySelector('[data-registration-root]');
    if (!root) return;

    root.dataset.enhanced = 'true';

    const form = root.querySelector('[data-registration-form]');
    if (!form) return;

    const nameInput = form.querySelector('[data-live-name]');
    const slugInput = form.querySelector('[data-live-slug]');
    const timezoneInput = form.querySelector('[data-live-timezone]');
    const ownerNameInput = form.querySelector('[data-live-owner-name]');
    const usernameInput = form.querySelector('[data-live-username]');
    const emailInput = form.querySelector('[data-live-email]');
    const passwordInput = form.querySelector('[data-live-password]');
    const confirmationInput = form.querySelector('[data-live-password-confirmation]');
    const termsInput = form.querySelector('input[name="terms"]');
    const passwordMatch = form.querySelector('[data-password-match]');

    const plate = root.querySelector('[data-plate]');
    const plateName = root.querySelector('[data-plate-name]');
    const plateSlug = root.querySelector('[data-plate-slug]');
    const plateType = root.querySelector('[data-plate-type]');
    const plateTimezone = root.querySelector('[data-plate-timezone]');
    const plateClock = root.querySelector('[data-plate-clock]');
    const plateZone = root.querySelector('[data-plate-zone]');
    const plateStatus = root.querySelector('[data-plate-status]');
    const plateReadyLabel = root.querySelector('[data-plate-ready-label]');
    const plateTrack = [...(root.querySelectorAll('[data-plate-track] span') ?? [])];
    const slugInline = root.querySelector('[data-slug-inline]');
    const slugNote = root.querySelector('[data-slug-note]');
    const timezoneClock = root.querySelector('[data-timezone-clock]');
    const timezoneZone = root.querySelector('[data-timezone-zone]');
    const timezoneDate = root.querySelector('[data-timezone-date]');
    const machine = root.querySelector('[data-workspace-press]');
    const lamps = [...(root.querySelectorAll('.press-lamp') ?? [])];

    let slugTouched = Boolean(slugInput?.value.trim());
    let lastMatchState = 'empty';
    let lastScore = null;

    const selectedType = () => {
        const checked = form.querySelector('input[name="type"]:checked');
        return checked?.closest('.reg-type')?.querySelector('.reg-type-title')?.textContent.trim() ?? 'Pendidikan';
    };

    const currentSlug = () => slugInput?.value.trim() || FALLBACK_SLUG;

    const syncAutoSlug = () => {
        if (!nameInput || !slugInput || slugTouched) return;
        slugInput.value = slugify(nameInput.value);
    };

    const updateSlugNote = () => {
        if (!slugNote) return;
        slugNote.textContent = slugTouched
            ? 'Alamat manual. Bisa diubah kapan saja.'
            : 'Dibuat otomatis dari nama. Bisa diubah kapan saja.';
    };

    const applyBrowserTimezone = () => {
        if (!timezoneInput || timezoneInput.dataset.timezoneDefaulted !== 'true') return;

        const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (!browserTimezone) return;

        const option = [...timezoneInput.options].find(
            (entry) => entry.value === browserTimezone,
        );
        if (option) timezoneInput.value = browserTimezone;
    };

    const updateClock = () => {
        if (!timezoneInput) return;

        const timeZone = timezoneInput.value || FALLBACK_TIMEZONE;
        const now = new Date();
        let time = '--:--';
        let zone = 'Waktu setempat';
        let date = 'Hari ini';

        try {
            time = new Intl.DateTimeFormat('id-ID', {
                timeZone,
                hour: '2-digit',
                minute: '2-digit',
                hourCycle: 'h23',
            })
                .format(now)
                .replace(/\./g, ':');

            const parts = new Intl.DateTimeFormat('id-ID', {
                timeZone,
                timeZoneName: 'short',
            }).formatToParts(now);

            zone =
                parts.find((part) => part.type === 'timeZoneName')?.value ??
                timeZone.split('/').pop().replace(/_/g, ' ');

            if (/^[+-]\d/.test(zone)) zone = `UTC${zone}`;

            date = new Intl.DateTimeFormat('id-ID', {
                timeZone,
                day: '2-digit',
                month: 'short',
            }).format(now);
        } catch {
            zone = timeZone.split('/').pop().replace(/_/g, ' ') || 'Waktu setempat';
        }

        if (timezoneClock) timezoneClock.textContent = time;
        if (timezoneZone) timezoneZone.textContent = zone;
        if (timezoneDate) timezoneDate.textContent = date;
        if (plateClock) plateClock.textContent = time;
        if (plateZone) plateZone.textContent = zone;
        if (plateTimezone) plateTimezone.textContent = timeZone;
    };

    const updatePasswordMatch = () => {
        if (!passwordMatch) return;

        const hasConfirmation = Boolean(confirmationInput?.value);
        const matches = hasConfirmation && confirmationInput.value === passwordInput?.value;
        const state = !hasConfirmation ? 'empty' : matches ? 'match' : 'mismatch';

        if (state === lastMatchState) return;

        passwordMatch.textContent =
            state === 'match'
                ? 'Password cocok.'
                : state === 'mismatch'
                  ? 'Password belum cocok.'
                  : 'Ketik ulang password yang sama.';
        passwordMatch.dataset.match = state;
        lastMatchState = state;
    };

    const readiness = () => {
        const typeChecked = Boolean(form.querySelector('input[name="type"]:checked'));

        return [
            Boolean(
                nameInput?.value.trim() &&
                    nameInput.checkValidity() &&
                    slugInput?.value.trim() &&
                    slugInput.checkValidity() &&
                    typeChecked &&
                    timezoneInput?.value &&
                    timezoneInput.checkValidity(),
            ),
            Boolean(
                ownerNameInput?.value.trim() &&
                    ownerNameInput.checkValidity() &&
                    usernameInput?.value.trim() &&
                    usernameInput.checkValidity() &&
                    emailInput?.value.trim() &&
                    emailInput.checkValidity(),
            ),
            Boolean(passwordInput?.value.length >= 8),
            Boolean(
                confirmationInput?.value &&
                    confirmationInput.value === passwordInput?.value,
            ),
            Boolean(termsInput?.checked),
        ];
    };

    const updatePreview = () => {
        const name = nameInput?.value.trim();
        const slug = currentSlug();
        const checks = readiness();
        const score = checks.filter(Boolean).length;

        if (plateName) plateName.textContent = name || 'Workspace baru';
        if (plateSlug) plateSlug.textContent = slug;
        if (plateType) plateType.textContent = selectedType();
        if (plateTimezone) plateTimezone.textContent = timezoneInput?.value || FALLBACK_TIMEZONE;
        if (slugInline) slugInline.textContent = `/o/${slug}/login`;

        if (score !== lastScore) {
            if (plateStatus) plateStatus.textContent = score === 5 ? 'Siap terbit' : 'Draf';
            if (plate) plate.dataset.ready = String(score === 5);
            if (machine) machine.dataset.ready = String(score === 5);
            lastScore = score;
        }

        if (plateReadyLabel) plateReadyLabel.textContent = `${score} / 5`;

        plateTrack.forEach((segment, index) => {
            segment.toggleAttribute('data-on', index < score);
        });

        lamps.forEach((lamp, index) => {
            lamp.toggleAttribute('data-on', index < score);
        });
    };

    const handleNameInput = () => {
        if (!slugTouched) syncAutoSlug();
        updateSlugNote();
    };

    const handleSlugInput = () => {
        if (!slugInput) return;

        const cleaned = cleanSlug(slugInput.value);
        if (cleaned !== slugInput.value) slugInput.value = cleaned;

        slugTouched = Boolean(slugInput.value.trim());
        updateSlugNote();
    };

    const handleSlugBlur = () => {
        if (!slugInput) return;

        const trimmed = slugInput.value.replace(/^[-_]+|[-_]+$/g, '');
        if (trimmed !== slugInput.value) slugInput.value = trimmed;

        slugTouched = Boolean(slugInput.value.trim());
        updateSlugNote();
    };

    const handleChange = (event) => {
        if (event.target === timezoneInput) updateClock();
        updatePasswordMatch();
        updatePreview();
    };

    form.addEventListener('input', (event) => {
        if (event.target === nameInput) handleNameInput();
        if (event.target === slugInput) handleSlugInput();
        if (event.target === timezoneInput) updateClock();
        updatePasswordMatch();
        updatePreview();
    });

    form.addEventListener('change', handleChange);

    slugInput?.addEventListener('blur', handleSlugBlur);

    root.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = root.querySelector(`#${button.dataset.passwordToggle}`);
            if (!target) return;

            const reveal = target.type === 'password';
            target.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(reveal));
            button.setAttribute(
                'aria-label',
                reveal ? 'Sembunyikan password' : 'Tampilkan password',
            );
        });
    });

    applyBrowserTimezone();
    if (!slugTouched) syncAutoSlug();
    updateSlugNote();
    updatePasswordMatch();
    updateClock();
    updatePreview();

    window.setInterval(updateClock, 30_000);

    const summary = root.querySelector('[data-error-summary]');
    if (summary) {
        window.requestAnimationFrame(() => summary.focus({ preventScroll: true }));
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRegistration, { once: true });
} else {
    initRegistration();
}
