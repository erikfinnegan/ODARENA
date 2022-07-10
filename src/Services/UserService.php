<?php

namespace OpenDominion\Services;

use OpenDominion\Models\User;

class UserService
{
    public function updateXp(User $user, string $action, int $xp = 0): void
    {

    }

    public function getXpForAction(string $action): int
    {
        $xp = 0;

        
    }

}
