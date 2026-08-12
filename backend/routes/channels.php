<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('community.{communitySlug}', function ($user, string $communitySlug): bool {
    $community = $user->communities()
        ->where('slug', $communitySlug)
        ->first();

    return $community !== null && (
        $user->hasCommunityPermission($community, 'ingestion.run')
        || $user->hasCommunityPermission($community, 'analytics.view')
    );
});
