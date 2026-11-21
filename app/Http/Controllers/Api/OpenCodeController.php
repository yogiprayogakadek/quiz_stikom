<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWithOpenCode;
use App\Services\OpenCode\OpenCodeClient;
use App\Services\OpenCode\OpenCodeException;
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
            'variant' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $variant = trim((string) ($validated['variant'] ?? ''));
        $variant = $variant === '' ? null : $variant;

        if ($variant !== null) {
            $model = trim((string) ($validated['model'] ?? ''));
            $model = $model === ''
                ? (string) config('services.opencode.model', 'deepseek-v4.1-flash-free')
                : $model;

            try {
                $client = app(OpenCodeClient::class);
            } catch (OpenCodeException $exception) {
                return response()->json([
                    'connected' => false,
                    'message' => $exception->getMessage(),
                ], 503);
            }

            $error = $this->validateVariant($client, $model, $variant);

            if ($error !== null) {
                return $error;
            }
        }

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
            $variant,
        );

        return response()->json([
            'result_key' => $key,
            'status' => 'queued',
        ], 202);
    }

    public function models(): JsonResponse
    {
        try {
            $client = app(OpenCodeClient::class);
            $models = $client->freeModels(fresh: true);
        } catch (OpenCodeException $exception) {
            return response()->json([
                'connected' => false,
                'message' => $exception->getMessage(),
            ], 503);
        }

        $data = [];

        foreach ($models as $model) {
            $id = $model['id'] ?? null;

            if (! is_string($id) || $id === '') {
                continue;
            }

            $name = $model['name'] ?? null;

            $data[] = [
                'id' => $id,
                'name' => is_string($name) && $name !== '' ? $name : $id,
                'variants' => $this->normalizeVariants($model['variants'] ?? []),
            ];
        }

        $defaultModel = trim((string) config('services.opencode.model', ''));

        if ($defaultModel === '') {
            $defaultModel = 'deepseek-v4.1-flash-free';
        }

        $default = null;

        foreach ($data as $model) {
            if ($model['id'] === $defaultModel) {
                $default = $model;
                break;
            }
        }

        if ($default === null && $data !== []) {
            $default = $data[0];
            $defaultModel = $default['id'];
        }

        $configuredVariant = trim((string) config('services.opencode.variant', ''));
        $defaultVariant = null;

        if ($configuredVariant !== '' && $default !== null) {
            foreach ($default['variants'] as $variant) {
                if ($variant['id'] === $configuredVariant) {
                    $defaultVariant = $configuredVariant;
                    break;
                }
            }
        }

        return response()->json([
            'connected' => true,
            'defaults' => [
                'model' => $defaultModel,
                'variant' => $defaultVariant,
                'agent' => (string) config('services.opencode.agent', ''),
            ],
            'data' => $data,
        ]);
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

    private function validateVariant(OpenCodeClient $client, string $model, string $variant): ?JsonResponse
    {
        try {
            $models = $client->models(fresh: true);
        } catch (OpenCodeException $exception) {
            return response()->json([
                'connected' => false,
                'message' => $exception->getMessage(),
            ], 503);
        }

        foreach ($models as $info) {
            if (($info['id'] ?? null) !== $model) {
                continue;
            }

            $variants = array_column($this->normalizeVariants($info['variants'] ?? []), 'id');

            if (in_array($variant, $variants, true)) {
                return null;
            }

            return response()->json([
                'message' => "Variant {$variant} is not available for {$model}.",
                'available_variants' => $variants,
            ], 422);
        }

        return response()->json([
            'message' => "Model {$model} is not available.",
            'available_variants' => [],
        ], 422);
    }

    /**
     * @return array<int, array{id: string, reasoning_effort: string}>
     */
    private function normalizeVariants(mixed $variants): array
    {
        if (! is_array($variants)) {
            return [];
        }

        $normalized = [];

        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $id = $variant['id'] ?? null;

            if (! is_string($id) || $id === '') {
                continue;
            }

            $settings = $variant['settings'] ?? [];
            $effort = is_array($settings) ? ($settings['reasoningEffort'] ?? null) : null;

            $normalized[] = [
                'id' => $id,
                'reasoning_effort' => is_string($effort) && $effort !== '' ? $effort : $id,
            ];
        }

        return $normalized;
    }
}
