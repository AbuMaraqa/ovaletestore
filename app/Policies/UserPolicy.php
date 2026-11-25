<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $target): bool
    {
        // Only super-admins (Gate::before) may edit another super-admin; nobody may edit an account
        // that holds permissions they lack (no password/email takeover of a more powerful user).
        return $user->can('users.update') && ! $target->hasRole('super-admin') && ! $this->isCustomer($target) && $this->outranks($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.delete') && $user->isNot($target) && ! $target->hasRole('super-admin') && ! $this->isCustomer($target) && $this->outranks($user, $target);
    }

    /** Store customers/traders are managed only from Customers/Traders (customers.* / traders.* permissions). */
    private function isCustomer(User $target): bool
    {
        return $target->hasExactRoles('customer');
    }

    /** The actor holds every permission the target has (so managing the target grants nothing new). */
    private function outranks(User $user, User $target): bool
    {
        return $user->is($target) || $target->getAllPermissions()->every(fn ($p) => $user->can($p->name));
    }
}
