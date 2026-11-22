<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function update(User $user, User $target): bool
    {
        // Only super-admins (Gate::before) may edit another super-admin.
        return $user->can('users.manage') && ! $target->hasRole('super-admin');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.manage') && $user->isNot($target) && ! $target->hasRole('super-admin');
    }
}
