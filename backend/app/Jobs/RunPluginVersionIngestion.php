<?php

namespace App\Jobs;

use App\Models\IngestionRun;
use App\Models\PluginVersion;
use App\Services\Ingestion\IngestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunPluginVersionIngestion implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $pluginVersionId,
        public readonly int $ingestionRunId,
    ) {}

    public function handle(IngestionService $service): void
    {
        $pluginVersion = PluginVersion::query()->findOrFail($this->pluginVersionId);
        $run = IngestionRun::query()->findOrFail($this->ingestionRunId);

        $service->run($pluginVersion, $run);
    }
}
