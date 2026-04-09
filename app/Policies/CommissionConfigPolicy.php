<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\CommissionConfig;

final class CommissionConfigPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    public function view(Admin $admin, CommissionConfig $config): bool
    {
        return true;
    }

    public function create(Admin $admin): bool
    {
        return $admin->role === 'super_admin';
    }

    /**
     * Controls activate / deactivate header actions in Filament.
     */
    public function update(Admin $admin, CommissionConfig $config): bool
    {
        return $admin->role === 'super_admin';
    }

    public function delete(Admin $admin, CommissionConfig $config): bool
    {
        return false;
    }
}
