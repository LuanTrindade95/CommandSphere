<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\RunPluginVersionIngestion;
use App\Models\Plugin;
use App\Services\Ingestion\IngestionService;
use App\Support\CorrelationId;
use App\Support\Telemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GitHubWebhookController extends Controller
{
    public function __invoke(Request $request, IngestionService $service): JsonResponse
    {
        $correlationId = CorrelationId::fromRequest($request);

        if (! $this->hasValidSignature($request)) {
            Telemetry::event('webhook.github.rejected', [
                'correlation_id' => $correlationId,
                'reason' => 'invalid_signature',
            ]);

            return response()->json([
                'message' => 'Invalid GitHub webhook signature.',
                'code' => 'webhook.invalid_signature',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $repository = $request->string('repository.full_name')->toString();
        $branch = Str::after($request->string('ref')->toString(), 'refs/heads/');

        if ($repository === '' || $branch === '') {
            Telemetry::event('webhook.github.accepted', [
                'correlation_id' => $correlationId,
                'repository' => $repository !== '' ? $repository : null,
                'branch' => $branch !== '' ? $branch : null,
                'queued' => 0,
            ]);

            return response()->json([
                'message' => 'Ignored GitHub webhook payload.',
                'code' => 'webhook.ignored',
                'queued' => 0,
            ]);
        }

        $queued = 0;

        Plugin::query()
            ->with(['versions' => fn ($query) => $query->orderByDesc('is_latest')->orderByDesc('id')])
            ->get()
            ->filter(fn (Plugin $plugin): bool => $this->matchesPlugin($plugin, $repository, $branch))
            ->each(function (Plugin $plugin) use ($service, $correlationId, &$queued): void {
                $pluginVersion = $plugin->versions->first();

                if ($pluginVersion === null) {
                    return;
                }

                $run = $service->start($pluginVersion, 'webhook', correlationId: $correlationId);

                if ($run->wasRecentlyCreated) {
                    RunPluginVersionIngestion::dispatch($pluginVersion->id, $run->id);
                    $queued++;
                }
            });

        Telemetry::event('webhook.github.accepted', [
            'correlation_id' => $correlationId,
            'repository' => $repository,
            'branch' => $branch,
            'queued' => $queued,
        ]);

        return response()->json([
            'message' => 'GitHub webhook processed.',
            'code' => 'webhook.processed',
            'queued' => $queued,
        ], $queued > 0 ? Response::HTTP_ACCEPTED : Response::HTTP_OK);
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = (string) config('commandsphere.github.webhook_secret');

        if ($secret === '') {
            return false;
        }

        $signature = $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return is_string($signature) && hash_equals($expected, $signature);
    }

    private function matchesPlugin(Plugin $plugin, string $repository, string $branch): bool
    {
        return $this->repositoryName($plugin) === $repository
            && $plugin->default_branch === $branch;
    }

    private function repositoryName(Plugin $plugin): string
    {
        $repository = $plugin->repository_url ?? '';

        if (preg_match('#github\.com[:/]([^/]+)/([^/.]+)#', $repository, $matches) === 1) {
            return $matches[1].'/'.$matches[2];
        }

        return $repository;
    }
}
