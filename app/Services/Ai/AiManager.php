<?php

namespace App\Services\Ai;

class AiManager
{
    public function __construct(private ?AiConfig $config = null)
    {
    }

    public function config(): AiConfig
    {
        return $this->config ??= AiConfig::current();
    }

    public function text(): TextClient
    {
        $config = $this->config();

        return $config->provider === 'opencode'
            ? new OpenCodeTextClient($config)
            : new OpenAiTextClient($config);
    }

    public function image(): ?ImageClient
    {
        $config = $this->config();

        if ($config->image['base_url'] === '' || blank($config->image['model'])) {
            return null;
        }

        if ($config->provider === 'opencode') {
            return blank($config->opencode['password']) ? null : new OpenCodeImageClient($config);
        }

        return blank($config->image['api_key']) ? null : new OpenAiImageClient($config);
    }
}
