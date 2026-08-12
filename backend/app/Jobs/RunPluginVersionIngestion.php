<?php

namespace App\Jobs;

use App\Models\IngestionRun;
use App\Models\PluginVersion;
use App\Services\Ingestion\IngestionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunPluginVersionIngestion implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $pluginVersionId,
        public readonly int $ingestionRunId,
    ) {}

    public function uniqueId(): string
    {
        return 'plugin-version:'.$this->pluginVersionId;
    }

    public function handle(IngestionService $service): void
    {
        $pluginVersion = PluginVersion::query()->findOrFail($this->pluginVersionId);
        $run = IngestionRun::query()->findOrFail($this->ingestionRunId);

        $service->run($pluginVersion, $run);
    }
}
