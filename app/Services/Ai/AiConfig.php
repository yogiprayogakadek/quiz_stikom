<?php

namespace App\Services\Ai;

final class AiConfig
{
    public function __construct(
        public readonly string $provider,
        public readonly array $openai,
        public readonly array $opencode,
        public readonly array $image,
    ) {
    }

    public static function current(): self
    {
        return static::fromSettings(app('app.settings'));
    }

    /**
     * @param  array<string, mixed>  $overrides  Nilai form; yang kosong diabaikan.
     */
    public static function fromSettings(object $settings, array $overrides = []): self
    {
        $value = function (string $key, mixed $default = null) use ($settings, $overrides): mixed {
            if (array_key_exists($key, $overrides) && filled($overrides[$key])) {
                return $overrides[$key];
            }

            $saved = $settings->getAttribute($key);

            return filled($saved) ? $saved : $default;
        };

        $provider = $value('ai_provider', 'openai') === 'opencode' ? 'opencode' : 'openai';

        $openai = [
            'base_url' => (string) $value('ai_base_url', config('services.ai.base_url')),
            'api_key' => $value('ai_api_key', config('services.ai.api_key')),
            'model' => (string) $value('ai_default_model', config('services.ai.default_model')),
            'timeout' => (int) $value('ai_timeout', config('services.ai.timeout', 60)),
        ];

        $opencode = [
            'base_url' => (string) $value('opencode_base_url', config('services.opencode.base_url')),
            'username' => (string) $value('opencode_username', config('services.opencode.username', 'opencode')),
            'password' => $value('opencode_password', config('services.opencode.password')),
            'model' => (string) $value('opencode_model', config('services.opencode.model')),
            'timeout' => (int) $value('opencode_timeout', config('services.opencode.timeout', 120)),
        ];

        $active = $provider === 'opencode' ? $opencode : $openai;

        $image = [
            'base_url' => (string) $value('image_base_url', $active['base_url']),
            'api_key' => $value('image_api_key', $active['api_key'] ?? null),
            'model' => (string) $value('image_model', ''),
            'timeout' => 150,
        ];

        return new self($provider, $openai, $opencode, $image);
    }

    public function textBaseUrl(): string
    {
        return $this->provider === 'opencode' ? $this->opencode['base_url'] : $this->openai['base_url'];
    }

    public function withTimeout(int $seconds): self
    {
        $seconds = max(1, $seconds);

        $openai = $this->openai;
        $openai['timeout'] = $seconds;

        $opencode = $this->opencode;
        $opencode['timeout'] = $seconds;

        return new self($this->provider, $openai, $opencode, $this->image);
    }

    public function defaultModel(): string
    {
        return $this->provider === 'opencode' ? $this->opencode['model'] : $this->openai['model'];
    }
}
