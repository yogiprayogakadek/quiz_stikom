@php
    $feedback = $attempt->feedback;
@endphp

<section class="{{ ($inline ?? false) ? 'mt-6' : 'mb-6' }} card" aria-labelledby="feedback-heading">
    <p class="pill pill-brand">Masukan</p>
    <h2 id="feedback-heading" class="mt-3 text-2xl">Bagaimana pengalaman ujianmu?</h2>
    <p class="mt-2 text-base text-ink-muted">
        Nama dan kelasmu disertakan bersama masukan ini agar pengajar di workspace dapat menindaklanjuti kendala dengan tepat. Setelah dikirim, pengingat tidak muncul lagi.
    </p>

    <form method="POST" action="{{ route('peserta.attempt.feedback', $attempt) }}" class="mt-5 space-y-4">
        @csrf

        <fieldset>
            <legend class="label">Nilai pengalamanmu</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" aria-label="Pilih nilai 1 sampai 5">
                @for ($i = 1; $i <= 5; $i++)
                    <label class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-xl border border-outline bg-page text-outline-strong transition has-[:checked]:border-sun-deep has-[:checked]:bg-sun-soft has-[:checked]:text-sun-deep has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand" title="{{ $i }} dari 5">
                        <input type="radio" name="rating" value="{{ $i }}" class="sr-only" required @checked((int) old('rating', $feedback?->rating) === $i)>
                        <x-icon name="star" :size="26" class="fill-current" />
                        <span class="sr-only">{{ $i }} dari 5</span>
                    </label>
                @endfor
            </div>
            @error('rating') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>

        <div>
            <label for="feedback_message" class="label">Komentar <span class="font-normal text-ink-subtle">(opsional)</span></label>
            <textarea id="feedback_message" name="message" class="input min-h-24" maxlength="1000" placeholder="Ceritakan yang membantu atau bagian yang perlu diperbaiki.">{{ old('message', $feedback?->message) }}</textarea>
            @error('message') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        @error('feedback') <p class="field-error">{{ $message }}</p> @enderror

        <div class="flex flex-wrap gap-3">
            <button type="submit" name="action" value="submit" class="btn btn-primary min-h-11">Kirim masukan</button>
            <button type="submit" name="action" value="dismiss" class="btn btn-ghost min-h-11" formnovalidate>Ingatkan saya nanti</button>
        </div>

        <p class="text-sm text-ink-muted">Kalau dilewati, pengingat akan muncul lagi saat kamu membuka dashboard.</p>
    </form>
</section>
