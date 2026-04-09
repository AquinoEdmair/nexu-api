<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Transaction;

final class TransactionPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    public function view(Admin $admin, Transaction $transaction): bool
    {
        return true;
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, Transaction $transaction): bool
    {
        return false;
    }

    public function delete(Admin $admin, Transaction $transaction): bool
    {
        return false;
    }
}
