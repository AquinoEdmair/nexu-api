<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\CryptoProviderInterface;
use App\Services\CryptoProviders\NowPaymentsCryptoProvider;
use App\Services\CryptoProviders\StubCryptoProvider;
use Illuminate\Support\ServiceProvider;

final class CryptoServiceProvider extends ServiceProvider
{
    /** @var array<string, class-string<CryptoProviderInterface>> */
    private const PROVIDERS = [
        'stub' => StubCryptoProvider::class,
        'nowpayments' => NowPaymentsCryptoProvider::class,
    ];

    public function register(): void
    {
        $this->app->singleton(CryptoProviderInterface::class, function (): CryptoProviderInterface {
            $driver = config('services.crypto.provider', 'stub');
            $class  = self::PROVIDERS[$driver] ?? StubCryptoProvider::class;

            return new $class();
        });
    }
}
