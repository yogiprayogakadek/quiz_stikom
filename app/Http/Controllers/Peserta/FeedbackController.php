<?php

namespace App\Http\Controllers\Peserta;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\ParticipantFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function store(Request $request, Attempt $attempt): RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        if (! $attempt->needsFeedbackPrompt()) {
            return back()->withErrors([
                'feedback' => 'Masukan sudah pernah dikirim atau belum tersedia.',
            ]);
        }

        $action = $request->input('action', 'submit');

        if ($action === 'dismiss') {
            ParticipantFeedback::query()->updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'organization_id' => $attempt->organization_id,
                ],
                [
                    'attempt_id' => $attempt->id,
                    'status' => ParticipantFeedback::STATUS_DISMISSED,
                    'responded_at' => now(),
                ],
            );

            return back()
                ->with('sukses', 'Baik, pengingat masukan akan muncul lagi nanti.')
                ->with('feedback_ditunda', true);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        ParticipantFeedback::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'organization_id' => $attempt->organization_id,
            ],
            [
                'attempt_id' => $attempt->id,
                'status' => ParticipantFeedback::STATUS_SUBMITTED,
                'rating' => $data['rating'],
                'message' => filled($data['message'] ?? null) ? trim($data['message']) : null,
                'responded_at' => now(),
            ],
        );

        return back()->with('sukses', 'Terima kasih, masukanmu sudah tersimpan.');
    }
}
