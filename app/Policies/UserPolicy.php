<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    public function view(Admin $admin, User $user): bool
    {
        return true;
    }

    public function create(Admin $admin): bool
    {
        return $admin->isSuperAdmin();
    }

    public function update(Admin $admin, User $user): bool
    {
        return $admin->isSuperAdmin();
    }

    public function delete(Admin $admin, User $user): bool
    {
        return false;
    }

    public function block(Admin $admin, User $user): bool
    {
        return $admin->isSuperAdmin() && $user->status === 'active';
    }

    public function unblock(Admin $admin, User $user): bool
    {
        return $admin->isSuperAdmin() && $user->status === 'blocked';
    }

    public function resetPassword(Admin $admin, User $user): bool
    {
        return $admin->isSuperAdmin();
    }
}
