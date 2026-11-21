<?php

namespace App\Console\Commands;

use App\Services\OpenCode\OpenCodeClient;
use App\Services\OpenCode\OpenCodeException;
use Illuminate\Console\Command;

class OpenCodeHealthCommand extends Command
{
    protected $signature = 'opencode:health';

    protected $description = 'Check the OpenCode API and list the free models';

    public function handle(OpenCodeClient $client): int
    {
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

        $this->info('OpenCode API is reachable.');
        $this->line('Free models: '.implode(', ', array_column($models, 'id')));

        return self::SUCCESS;
    }
}
