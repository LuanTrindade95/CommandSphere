<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\RunPluginVersionIngestion;
use App\Models\Plugin;
use App\Services\Ingestion\IngestionService;
use Illuminate\Http\JsonResponse;

class PluginSyncController extends Controller
{
    public function __invoke(Plugin $plugin, IngestionService $service): JsonResponse
    {
        $plugin->loadMissing('versions');

        $pluginVersion = $plugin->versions()
            ->where('is_latest', true)
            ->latest('id')
            ->first()
            ?? $plugin->versions()->latest('id')->firstOrFail();

        $run = $service->start($pluginVersion);

        RunPluginVersionIngestion::dispatch($pluginVersion->id, $run->id);

        return response()->json([
            'ingestion_run' => [
                'id' => $run->id,
                'plugin_version_id' => $run->plugin_version_id,
                'status' => $run->status,
                'stats' => $run->stats,
                'log' => $run->log,
            ],
        ], 202);
    }
}
