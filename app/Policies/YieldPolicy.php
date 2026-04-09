<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\YieldLog;

final class YieldPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    public function view(Admin $admin, YieldLog $yieldLog): bool
    {
        return true;
    }

    public function create(Admin $admin): bool
    {
        return true;
    }

    public function update(Admin $admin, YieldLog $yieldLog): bool
    {
        return false;
    }

    public function delete(Admin $admin, YieldLog $yieldLog): bool
    {
        return false;
    }
}
