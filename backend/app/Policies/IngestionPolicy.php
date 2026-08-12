<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\User;

class IngestionPolicy
{
    public function run(User $user, Community $community): bool
    {
        return $user->hasCommunityPermission($community, 'ingestion.run');
    }
}
