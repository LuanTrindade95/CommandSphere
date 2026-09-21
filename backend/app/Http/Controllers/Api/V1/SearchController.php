<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommandResource;
use App\Models\Command;
use App\Models\Community;
use App\Models\User;
use App\Services\Auth\OptionalBearerUserResolver;
use App\Services\Discovery\DiscoveryAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class SearchController extends Controller
{
    public function __invoke(Request $request, DiscoveryAccess $access, OptionalBearerUserResolver $userResolver): JsonResponse
    {
        $user = $userResolver->resolve($request);
        $term = (string) $request->query('q', '');
        $filters = $this->filters($request, $access, $user);

        $raw = Command::search($term)
            ->options([
                'filter' => $filters,
                'facets' => ['community', 'plugin', 'category'],
                'limit' => 10,
                'attributesToRetrieve' => ['id'],
            ])
            ->raw();

        $ids = collect(Arr::get($raw, 'hits', []))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

        $commands = $access->commands($user)
            ->with(['category', 'pluginVersion.plugin.community'])
            ->withCount('views')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Command $command): int => array_search($command->id, $ids, true))
            ->values();

        return response()->json([
            'data' => CommandResource::collection($commands)->resolve($request),
            'facets' => [
                'community' => Arr::get($raw, 'facetDistribution.community', []),
                'plugin' => Arr::get($raw, 'facetDistribution.plugin', []),
                'category' => Arr::get($raw, 'facetDistribution.category', []),
            ],
            'meta' => [
                'query' => $term,
                'estimated_total_hits' => Arr::get($raw, 'estimatedTotalHits', 0),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function filters(Request $request, DiscoveryAccess $access, ?User $user): array
    {
        $accessibleCommunitySlugs = $access->communities($user)
            ->pluck('slug')
            ->values();

        $filters = [
            'community IN ['.$accessibleCommunitySlugs
                ->map(fn (string $slug): string => $this->quote($slug))
                ->implode(', ').']',
        ];

        $community = $request->query('community');

        if (is_string($community) && $community !== '') {
            $filters[] = 'community = '.$this->quote($this->communitySlug($community));
        }

        foreach (['plugin', 'category'] as $filter) {
            $value = $request->query($filter);

            if (is_string($value) && $value !== '') {
                $filters[] = $filter.' = '.$this->quote($value);
            }
        }

        return $filters;
    }

    private function communitySlug(string $value): string
    {
        if (ctype_digit($value)) {
            $community = Community::query()->find((int) $value);

            if ($community instanceof Community) {
                return $community->slug;
            }
        }

        return $value;
    }

    private function quote(string $value): string
    {
        return '"'.str_replace('"', '\"', $value).'"';
    }
}
