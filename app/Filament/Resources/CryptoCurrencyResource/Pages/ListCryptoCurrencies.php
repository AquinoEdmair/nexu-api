<?php

declare(strict_types=1);

namespace App\Filament\Resources\CryptoCurrencyResource\Pages;

use App\Contracts\CryptoProviderInterface;
use App\Filament\Resources\CryptoCurrencyResource;
use App\Models\CryptoCurrency;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

final class ListCryptoCurrencies extends ListRecords
{
    protected static string $resource = CryptoCurrencyResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFromNowPayments')
                ->label('Sincronizar desde NowPayments')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sincronizar monedas')
                ->modalDescription('Se importarán las monedas activas en tu cuenta de NowPayments. Las existentes no se eliminarán.')
                ->action(function (): void {
                    try {
                        $provider = app(CryptoProviderInterface::class);
                        $codes    = $provider->getMerchantCurrencies();

                        // Map from NowPayments codes to display names / networks
                        $knownMeta = [
                            'btc'        => ['symbol' => 'BTC',  'name' => 'Bitcoin',        'network' => 'Bitcoin'],
                            'eth'        => ['symbol' => 'ETH',  'name' => 'Ethereum',        'network' => 'ERC20'],
                            'usdttrc20'  => ['symbol' => 'USDT', 'name' => 'Tether (TRC20)',  'network' => 'TRC20'],
                            'usdterc20'  => ['symbol' => 'USDT-ERC20', 'name' => 'Tether (ERC20)', 'network' => 'ERC20'],
                            'ltc'        => ['symbol' => 'LTC',  'name' => 'Litecoin',        'network' => 'Litecoin'],
                            'bnbbsc'     => ['symbol' => 'BNB',  'name' => 'BNB (BSC)',       'network' => 'BSC'],
                            'usdcbsc'    => ['symbol' => 'USDC-BSC', 'name' => 'USDC (BSC)',  'network' => 'BSC'],
                            'usdcsol'    => ['symbol' => 'USDC-SOL', 'name' => 'USDC (SOL)',  'network' => 'Solana'],
                            'sol'        => ['symbol' => 'SOL',  'name' => 'Solana',          'network' => 'Solana'],
                            'trx'        => ['symbol' => 'TRX',  'name' => 'TRON',            'network' => 'TRC20'],
                        ];

                        $created = 0;
                        foreach ($codes as $i => $code) {
                            $meta = $knownMeta[$code] ?? [
                                'symbol'  => strtoupper($code),
                                'name'    => strtoupper($code),
                                'network' => null,
                            ];

                            $existed = CryptoCurrency::where('now_payments_code', $code)->exists();

                            CryptoCurrency::firstOrCreate(
                                ['now_payments_code' => $code],
                                [
                                    'symbol'     => $meta['symbol'],
                                    'name'       => $meta['name'],
                                    'network'    => $meta['network'],
                                    'is_active'  => true,
                                    'sort_order' => $i,
                                ]
                            );

                            if (! $existed) {
                                $created++;
                            }
                        }

                        Notification::make()
                            ->title('Sincronización completada')
                            ->body("{$created} moneda(s) nueva(s) importada(s). Total en cuenta: " . count($codes) . '.')
                            ->success()
                            ->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->title('Error al sincronizar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            CreateAction::make(),
        ];
    }
}
