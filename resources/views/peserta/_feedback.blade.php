@php
    $feedback = $attempt->feedback;
@endphp

<section class="{{ ($inline ?? false) ? 'mt-6' : 'mb-6' }} card" aria-labelledby="feedback-heading">
    <p class="pill pill-brand">Masukan</p>
    <h2 id="feedback-heading" class="mt-3 text-2xl">Bagaimana pengalaman ujianmu?</h2>
    <p class="mt-2 text-base text-ink-muted">
        Masukan ini membantu admin workspace memperbaiki pengalaman belajar. Setelah dikirim, pengingat tidak muncul lagi.
    </p>

    <form method="POST" action="{{ route('peserta.attempt.feedback', $attempt) }}" class="mt-5 space-y-4">
        @csrf

        <fieldset>
            <legend class="label">Nilai pengalamanmu</legend>
            <div class="mt-2 flex gap-1" aria-label="Pilih nilai 1 sampai 5">
                @for ($i = 1; $i <= 5; $i++)
                    <label class="cursor-pointer text-outline-strong has-[:checked]:text-sun-deep" title="{{ $i }} dari 5">
                        <input type="radio" name="rating" value="{{ $i }}" class="sr-only" required @checked((int) old('rating', $feedback?->rating) === $i)>
                        <x-icon name="star" :size="32" class="fill-current" />
                        <span class="sr-only">{{ $i }} dari 5</span>
                    </label>
                @endfor
            </div>
            @error('rating') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>

        <div>
            <label for="feedback_message" class="label">Komentar <span class="font-normal text-ink-subtle">(opsional)</span></label>
            <textarea id="feedback_message" name="message" class="input min-h-24" maxlength="1000" placeholder="Ceritakan yang membantu atau yang perlu diperbaiki.">{{ old('message', $feedback?->message) }}</textarea>
            @error('message') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        @error('feedback') <p class="field-error">{{ $message }}</p> @enderror

        <div class="flex flex-wrap gap-3">
            <button type="submit" name="action" value="submit" class="btn btn-primary">Kirim masukan</button>
            <button type="submit" name="action" value="dismiss" class="btn btn-ghost" formnovalidate>Ingatkan saya nanti</button>
        </div>

        <p class="text-sm text-ink-muted">Kalau dilewati, pengingat akan muncul lagi saat kamu membuka dashboard.</p>
    </form>
</section>
