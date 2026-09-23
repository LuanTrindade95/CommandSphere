<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\RunPluginVersionIngestion;
use App\Models\Plugin;
use App\Services\Ingestion\IngestionService;
use App\Support\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PluginSyncController extends Controller
{
    public function __invoke(Request $request, Plugin $plugin, IngestionService $service): JsonResponse
    {
        $plugin->loadMissing('versions');

        $pluginVersion = $plugin->versions()
            ->where('is_latest', true)
            ->latest('id')
            ->first()
            ?? $plugin->versions()->latest('id')->firstOrFail();

        $run = $service->start($pluginVersion, 'manual', correlationId: CorrelationId::fromRequest($request));

        if ($run->wasRecentlyCreated) {
            RunPluginVersionIngestion::dispatch($pluginVersion->id, $run->id);
        }

        return response()->json([
            'ingestion_run' => [
                'id' => $run->id,
                'plugin_version_id' => $run->plugin_version_id,
                'source' => $run->source,
                'correlation_id' => $run->correlation_id,
                'status' => $run->status,
                'stats' => $run->stats,
                'log' => $run->log,
            ],
            'queued' => $run->wasRecentlyCreated,
        ], $run->wasRecentlyCreated ? 202 : 200);
    }
}
