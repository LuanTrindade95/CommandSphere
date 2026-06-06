<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\User;

class PluginPolicy
{
    public function manage(User $user, Community $community): bool
    {
        return $user->hasCommunityPermission($community, 'plugins.manage');
    }
}
