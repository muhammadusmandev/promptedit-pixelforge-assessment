<?php

namespace App\Console\Commands;

use App\Models\Generation;
use App\Services\PixelforgeService;
use App\Services\GenerationStatusService;
use Illuminate\Console\Command;
use Throwable;

class PollPixelforgeGenerations extends Command
{
    protected $signature = 'pixelforge:poll-generations';

    protected $description = 'Synchronize pending generations with Pixelforge';

    public function handle(
        PixelforgeService $pixelforge,
        GenerationStatusService $statusService
    ): int {
        $this->info('Checking pending Pixelforge generations...');

        $checked = 0;

        Generation::query()
            ->whereNotNull('provider_id')
            ->whereIn('status', ['queued', 'processing'])
            ->orderBy('id')
            ->chunkById(50, function ($generations) use (
                $pixelforge,
                $statusService,
                &$checked
            ) {
                foreach ($generations as $generation) {
                    try {
                        $providerData =
                            $pixelforge->retrieveGeneration(
                                $generation->provider_id
                            );

                        $statusService->apply(
                            $generation,
                            $providerData
                        );

                        $checked++;

                    } catch (Throwable $exception) {
                        // One failed request must not stop other generations.
                        report($exception);

                        $this->warn(
                            "Could not synchronize generation #{$generation->id}"
                        );
                    }
                }
            });

        $this->info("Checked {$checked} generations.");

        return self::SUCCESS;
    }
}
