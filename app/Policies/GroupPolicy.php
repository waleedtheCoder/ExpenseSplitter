<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function view(User $user, Group $group): bool
    {
        return $group->members()->where('users.id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Only the creator can rename/delete the group or manage its membership. */
    public function update(User $user, Group $group): bool
    {
        return $group->created_by === $user->id;
    }

    public function delete(User $user, Group $group): bool
    {
        return $group->created_by === $user->id;
    }

    /** Any member may log an expense or record a settlement. */
    public function addExpense(User $user, Group $group): bool
    {
        return $this->view($user, $group);
    }
}
