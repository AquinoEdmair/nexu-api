<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CryptoCurrency;
use Illuminate\Database\Seeder;

final class CryptoCurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            [
                'name'               => 'Tether (TRC20)',
                'symbol'             => 'USDT',
                'now_payments_code'  => 'usdttrc20',
                'network'            => 'TRC20',
                'is_active'          => true,
                'sort_order'         => 0,
            ],
            [
                'name'               => 'Bitcoin',
                'symbol'             => 'BTC',
                'now_payments_code'  => 'btc',
                'network'            => 'Bitcoin',
                'is_active'          => true,
                'sort_order'         => 1,
            ],
            [
                'name'               => 'Ethereum',
                'symbol'             => 'ETH',
                'now_payments_code'  => 'eth',
                'network'            => 'ERC20',
                'is_active'          => true,
                'sort_order'         => 2,
            ],
        ];

        foreach ($currencies as $currency) {
            CryptoCurrency::firstOrCreate(
                ['symbol' => $currency['symbol']],
                $currency,
            );
        }
    }
}
