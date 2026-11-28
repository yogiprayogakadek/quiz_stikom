<?php

namespace App\Http\Controllers;

use App\Events\SessionMonitorUpdated;
use App\Models\Attempt;
use App\Models\AttemptSnapshot;
use App\Services\OrganizationUsage;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttemptSnapshotController extends Controller
{
    public function store(Request $request, Attempt $attempt, OrganizationUsage $usage): JsonResponse
    {
        abort_unless((int) $attempt->user_id === (int) $request->user()->id, 403);

        if ($attempt->status !== 'berjalan') {
            return response()->json(['message' => 'Attempt sudah selesai.'], 403);
        }

        $validated = $request->validate([
            'image' => ['required', 'file', 'max:256'],
            'capture_source' => ['required', 'in:screen,webcam'],
            'trigger_type' => ['required', 'string', 'max:32'],
            'question_urutan' => ['nullable', 'integer', 'min:1', 'max:500'],
            'luminance' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        $upload = $request->file('image');
        $extension = strtolower($upload->getClientOriginalExtension() ?: $upload->extension() ?: 'webp');
        if (! in_array($extension, ['webp', 'jpg', 'jpeg', 'png'], true)) {
            return response()->json(['message' => 'Format gambar bukti harus WebP, JPEG, atau PNG.'], 422);
        }

        $source = $validated['capture_source'];
        $cap = $source === 'screen'
            ? AttemptSnapshot::MAX_SCREEN_PER_ATTEMPT
            : AttemptSnapshot::MAX_WEBCAM_PER_ATTEMPT;

        $existingQuery = AttemptSnapshot::withoutGlobalScopes()
            ->where('attempt_id', $attempt->id)
            ->where('capture_source', $source)
            ->orderBy('id');

        $existingCount = (clone $existingQuery)->count();
        if ($existingCount >= $cap) {
            $toPrune = (clone $existingQuery)->limit($existingCount - $cap + 1)->get();
            foreach ($toPrune as $old) {
                Storage::disk('local')->delete($old->path);
                $old->delete();
            }
        }

        $sizeBytes = max(1, (int) $upload->getSize());
        $orgId = (int) $attempt->organization_id;

        if ($orgId > 0 && ! $usage->canStore($orgId, $sizeBytes)) {
            return response()->json(['message' => 'Kuota penyimpanan workspace penuh.'], 422);
        }

        $path = 'attempt-snapshots/'.$orgId.'/'.$attempt->id.'/'.Str::uuid().'.'.$extension;
        if (! Storage::disk('local')->putFileAs(dirname($path), $upload, basename($path))) {
            return response()->json(['message' => 'Gagal menyimpan gambar bukti.'], 500);
        }

        $snapshot = AttemptSnapshot::withoutGlobalScopes()->create([
            'organization_id' => $attempt->organization_id,
            'attempt_id' => $attempt->id,
            'question_urutan' => $validated['question_urutan'] ?? null,
            'capture_source' => $source,
            'trigger_type' => $validated['trigger_type'],
            'path' => $path,
            'size_bytes' => $sizeBytes,
            'luminance' => $validated['luminance'] ?? null,
            'captured_at' => now(),
        ]);

        SessionMonitorUpdated::safeDispatch($attempt->quiz_session_id, [
            'action' => 'snapshot_uploaded',
            'attempt_id' => $attempt->id,
            'capture_source' => $source,
        ]);

        return response()->json([
            'ok' => true,
            'snapshot_id' => $snapshot->id,
        ], 201);
    }

    public function show(AttemptSnapshot $snapshot): StreamedResponse|BinaryFileResponse
    {
        $orgId = app(OrganizationContext::class)->id();
        abort_unless($orgId !== null && (int) $snapshot->organization_id === (int) $orgId, 404);
        abort_unless(Storage::disk('local')->exists($snapshot->path), 404);

        $ext = strtolower(pathinfo($snapshot->path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'image/webp',
        };

        return Storage::disk('local')->response($snapshot->path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
