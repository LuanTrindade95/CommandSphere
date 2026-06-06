<?php

namespace App\Console\Commands;

use App\Jobs\RunPluginVersionIngestion;
use App\Models\Plugin;
use App\Services\Ingestion\IngestionService;
use Illuminate\Console\Command;

class SyncScheduledPlugins extends Command
{
    protected $signature = 'commandsphere:sync-scheduled';

    protected $description = 'Queue ingestion runs for latest plugin versions.';

    public function handle(IngestionService $service): int
    {
        $queued = 0;

        Plugin::query()
            ->with(['versions' => fn ($query) => $query->orderByDesc('is_latest')->orderByDesc('id')])
            ->orderBy('id')
            ->each(function (Plugin $plugin) use ($service, &$queued): void {
                $pluginVersion = $plugin->versions->first();

                if ($pluginVersion === null) {
                    return;
                }

                $run = $service->start($pluginVersion);
                RunPluginVersionIngestion::dispatch($pluginVersion->id, $run->id);
                $queued++;
            });

        $this->components->info("Queued {$queued} scheduled plugin sync runs.");

        return self::SUCCESS;
    }
}
