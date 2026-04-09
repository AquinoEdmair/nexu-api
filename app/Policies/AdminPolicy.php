<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

final class AdminPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->isSuperAdmin();
    }

    public function view(Admin $admin, Admin $model): bool
    {
        return $admin->isSuperAdmin();
    }

    public function create(Admin $admin): bool
    {
        return $admin->isSuperAdmin();
    }

    public function update(Admin $admin, Admin $model): bool
    {
        return $admin->isSuperAdmin();
    }

    public function delete(Admin $admin, Admin $model): bool
    {
        return false;
    }
}
