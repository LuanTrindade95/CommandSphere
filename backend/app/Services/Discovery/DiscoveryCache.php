<?php

namespace App\Services\Discovery;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;

class DiscoveryCache
{
    private const VERSION_KEY = 'commandsphere:discovery:version';

    public function remember(?User $user, string $key, Closure $callback, int $seconds = 300): mixed
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);
        $scope = $user instanceof User ? 'u'.$user->id : 'public';

        return Cache::remember(
            "commandsphere:discovery:v{$version}:{$scope}:{$key}",
            $seconds,
            $callback,
        );
    }

    public function invalidate(): void
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);

        Cache::forever(self::VERSION_KEY, $version + 1);
    }
}
