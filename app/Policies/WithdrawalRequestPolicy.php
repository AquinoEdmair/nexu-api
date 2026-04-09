<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\WithdrawalRequest;

final class WithdrawalRequestPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    public function view(Admin $admin, WithdrawalRequest $request): bool
    {
        return true;
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, WithdrawalRequest $request): bool
    {
        return false;
    }

    public function delete(Admin $admin, WithdrawalRequest $request): bool
    {
        return false;
    }

    public function approve(Admin $admin, WithdrawalRequest $request): bool
    {
        return $request->status === 'pending';
    }

    public function reject(Admin $admin, WithdrawalRequest $request): bool
    {
        return in_array($request->status, ['pending', 'approved'], strict: true);
    }

    public function complete(Admin $admin, WithdrawalRequest $request): bool
    {
        return $request->status === 'approved';
    }
}
