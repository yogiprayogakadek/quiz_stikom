<?php

namespace App\Http\Controllers\Peserta;

use App\Http\Controllers\Controller;
use App\Models\SessionMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionMessageController extends Controller
{
    public function tutup(Request $request, SessionMessage $message): JsonResponse
    {
        abort_unless($message->user_id === $request->user()->id, 403);

        if ($message->ditutup_at === null) {
            $message->forceFill(['ditutup_at' => now()])->save();
        }

        return response()->json(['ok' => true]);
    }
}
