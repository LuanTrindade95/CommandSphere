<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\User;

class AnalyticsPolicy
{
    public function view(User $user, Community $community): bool
    {
        return $user->hasCommunityPermission($community, 'analytics.view');
    }
}
