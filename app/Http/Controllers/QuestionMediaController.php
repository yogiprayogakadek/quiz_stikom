<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\OrganizationMembership;
use App\Models\QuestionMedia;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuestionMediaController extends Controller
{
    public function __invoke(Request $request, QuestionMedia $media): BinaryFileResponse
    {
        if (! $request->hasValidSignature()) {
            $user = $request->user();
            abort_unless($user && app(OrganizationContext::class)->id() === $media->organization_id, 403);
            $membership = OrganizationMembership::query()
                ->where('organization_id', $media->organization_id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();
            abort_unless($membership, 403);

            if ($user->isPeserta()) {
                $canView = Attempt::query()
                    ->where('user_id', $user->id)
                    ->whereHas('quizSession', fn ($query) => $query->where('organization_id', $media->organization_id))
                    ->where(function ($query) use ($media) {
                        $query->whereHas('attemptQuestions', fn ($attemptQuestions) => $attemptQuestions->where('question_id', $media->question_id))
                            ->orWhereHas('quizSession.sessionQuestions', fn ($sessionQuestions) => $sessionQuestions->where('question_id', $media->question_id));
                    })
                    ->exists();
                abort_unless($canView, 403);
            } else {
                abort_unless(in_array($membership->role, ['owner', 'admin'], true), 403);
            }
        }

        abort_unless(Storage::disk('local')->exists($media->path), 404);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $media->original_name ?: 'gambar-soal') ?: 'gambar-soal';

        return response()->file(Storage::disk('local')->path($media->path), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
