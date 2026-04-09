<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Models\Admin;
use App\Services\AdminAuthService;
use Filament\Pages\SimplePage;
use Illuminate\Support\Facades\Auth;

class TwoFactorChallenge extends SimplePage
{
    protected static string $view  = 'filament.pages.auth.two-factor-challenge';
    protected static ?string $slug = 'two-factor-challenge';

    public string $code         = '';
    public string $recoveryCode = '';
    public string $activeTab    = 'totp';

    public ?string $error         = null;
    public ?string $recoveryError = null;

    public function mount(): void
    {
        if (! session()->has('auth.pending_2fa')) {
            $this->redirect('/admin/login');
        }
    }

    public function verifyTotp(): void
    {
        $this->error = null;

        $adminId = session('auth.pending_2fa');
        $admin   = Admin::find($adminId);

        if (! $admin) {
            session()->forget('auth.pending_2fa');
            $this->redirect('/admin/login');

            return;
        }

        $ip    = request()->ip() ?? '0.0.0.0';
        $valid = app(AdminAuthService::class)->verifyTwoFactor($admin, $this->code, $ip);

        if (! $valid) {
            $this->error = 'Código incorrecto o cuenta bloqueada temporalmente.';

            return;
        }

        session()->forget('auth.pending_2fa');
        Auth::login($admin);

        $this->redirect('/admin');
    }

    public function verifyRecovery(): void
    {
        $this->recoveryError = null;

        $adminId = session('auth.pending_2fa');
        $admin   = Admin::find($adminId);

        if (! $admin) {
            session()->forget('auth.pending_2fa');
            $this->redirect('/admin/login');

            return;
        }

        $ip    = request()->ip() ?? '0.0.0.0';
        $valid = app(AdminAuthService::class)->useRecoveryCode($admin, $this->recoveryCode, $ip);

        if (! $valid) {
            $this->recoveryError = 'Código de recuperación inválido.';

            return;
        }

        session()->forget('auth.pending_2fa');
        Auth::login($admin);

        $this->redirect('/admin');
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->error     = null;
        $this->recoveryError = null;
    }
}
