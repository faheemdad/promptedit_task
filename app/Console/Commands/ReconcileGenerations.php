<?php

namespace App\Console\Commands;

use App\Actions\Generations\GenerationLifecycle;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Console\Command;

/**
 * Safety net for missed webhooks: polls Pixelforge for generations that
 * have been in flight for a while without news.
 */
class ReconcileGenerations extends Command
{
    protected $signature = 'generations:reconcile {--limit=20}';

    protected $description = 'Poll Pixelforge for in-flight generations whose webhooks have not arrived';

    public function handle(PixelforgeClient $client, GenerationLifecycle $lifecycle): int
    {
        $stale = Generation::query()
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->whereNotNull('provider_id')
            ->where('updated_at', '<', now()->subSeconds(config('pixelforge.reconcile_after_seconds')))
            ->oldest('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($stale as $generation) {
            try {
                $lifecycle->applyRemote($client->getGeneration($generation->provider_id));
            } catch (PixelforgeException $e) {
                $this->warn("{$generation->id}: {$e->errorCode}");

                if ($e->errorCode === 'rate_limit_exceeded') {
                    break;
                }
            }
        }

        $this->info("Reconciled {$stale->count()} generation(s).");

        return self::SUCCESS;
    }
}
