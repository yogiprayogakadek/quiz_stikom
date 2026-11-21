<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWithOpenCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OpenCodeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => ['required'],
            'categories' => ['sometimes', 'array', 'max:20'],
            'categories.*' => ['string', 'max:100'],
            'fields' => ['sometimes', 'array', 'max:50'],
            'fields.*' => ['string', 'max:100'],
            'model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'agent' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $key = (string) Str::uuid();

        Cache::put('opencode:result:'.$key, [
            'status' => 'queued',
            'created_at' => now()->toIso8601String(),
        ], now()->addDay());

        ProcessWithOpenCode::dispatch(
            $key,
            $validated,
            $validated['model'] ?? null,
            $validated['agent'] ?? null,
        );

        return response()->json([
            'result_key' => $key,
            'status' => 'queued',
        ], 202);
    }

    public function show(string $key): JsonResponse
    {
        $result = Cache::get('opencode:result:'.$key);

        if (! is_array($result)) {
            return response()->json(['message' => 'Result not found.'], 404);
        }

        $status = $result['status'] ?? 'unknown';

        return response()->json($result, $status === 'failed' ? 422 : 200);
    }
}
