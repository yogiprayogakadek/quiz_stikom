<?php

namespace App\Console\Commands;

use App\Services\OpenCode\OpenCodeClient;
use App\Services\OpenCode\OpenCodeException;
use Illuminate\Console\Command;

class OpenCodeHealthCommand extends Command
{
    protected $signature = 'opencode:health';

    protected $description = 'Check the OpenCode API and list the free models';

    public function handle(): int
    {
        try {
            $client = app(OpenCodeClient::class);
        } catch (OpenCodeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $client->health()) {
            $this->error('OpenCode API is not reachable.');

            return self::FAILURE;
        }

        try {
            $models = $client->freeModels();
        } catch (OpenCodeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $configured = trim((string) config('services.opencode.variant', ''));

        $this->info('OpenCode API is reachable.');
        $this->line('Free models: '.implode(', ', array_column($models, 'id')));

        $rows = [];

        foreach ($models as $model) {
            $variants = [];

            foreach ((array) ($model['variants'] ?? []) as $variant) {
                if (is_array($variant) && isset($variant['id'])) {
                    $variants[] = (string) $variant['id'];
                }
            }

            $rows[] = [(string) ($model['id'] ?? ''), $variants === [] ? '-' : implode(', ', $variants)];
        }

        $this->table(['Model', 'Variants'], $rows);
        $this->line('Configured variant: '.($configured === '' ? 'none' : $configured));

        return self::SUCCESS;
    }
}
