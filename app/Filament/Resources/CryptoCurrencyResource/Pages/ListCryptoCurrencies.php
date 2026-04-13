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
use Illuminate\Support\Facades\Log;

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

                        // Log raw response to debug what NowPayments actually sends
                        Log::info('NowPayments getMerchantCurrencies raw codes', ['codes' => $codes]);

                        if (empty($codes)) {
                            Notification::make()
                                ->title('Sin monedas')
                                ->body('NowPayments devolvió una lista vacía. Verifica que tengas monedas configuradas en tu cuenta.')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Map from NowPayments codes (always lowercased before lookup) to display names / networks
                        $knownMeta = [
                            'btc'        => ['symbol' => 'BTC',       'name' => 'Bitcoin',        'network' => 'Bitcoin'],
                            'eth'        => ['symbol' => 'ETH',       'name' => 'Ethereum',        'network' => 'ERC20'],
                            'usdttrc20'  => ['symbol' => 'USDT',      'name' => 'Tether (TRC20)',  'network' => 'TRC20'],
                            'usdterc20'  => ['symbol' => 'USDT-ERC20','name' => 'Tether (ERC20)',  'network' => 'ERC20'],
                            'ltc'        => ['symbol' => 'LTC',       'name' => 'Litecoin',        'network' => 'Litecoin'],
                            'bnbbsc'     => ['symbol' => 'BNB',       'name' => 'BNB (BSC)',       'network' => 'BSC'],
                            'usdcbsc'    => ['symbol' => 'USDC-BSC',  'name' => 'USDC (BSC)',      'network' => 'BSC'],
                            'usdcsol'    => ['symbol' => 'USDC-SOL',  'name' => 'USDC (SOL)',      'network' => 'Solana'],
                            'sol'        => ['symbol' => 'SOL',       'name' => 'Solana',          'network' => 'Solana'],
                            'trx'        => ['symbol' => 'TRX',       'name' => 'TRON',            'network' => 'TRC20'],
                            'matic'      => ['symbol' => 'MATIC',     'name' => 'Polygon',         'network' => 'Polygon'],
                            'maticpolygon' => ['symbol' => 'MATIC',   'name' => 'Polygon',         'network' => 'Polygon'],
                            'xrp'        => ['symbol' => 'XRP',       'name' => 'Ripple',          'network' => 'XRP'],
                            'doge'       => ['symbol' => 'DOGE',      'name' => 'Dogecoin',        'network' => 'Dogecoin'],
                            'ada'        => ['symbol' => 'ADA',       'name' => 'Cardano',         'network' => 'Cardano'],
                        ];

                        $created  = 0;
                        $updated  = 0;
                        $skipped  = 0;
                        $errors   = [];

                        foreach ($codes as $i => $rawCode) {
                            // Always normalize to lowercase for lookup
                            $code = strtolower((string) $rawCode);

                            $meta = $knownMeta[$code] ?? [
                                'symbol'  => strtoupper($code),
                                'name'    => strtoupper($code),
                                'network' => null,
                            ];

                            try {
                                $existing = CryptoCurrency::where('now_payments_code', $code)
                                    ->orWhere('symbol', $meta['symbol'])
                                    ->first();

                                if ($existing) {
                                    // Update now_payments_code if missing, keep other fields
                                    $existing->update([
                                        'now_payments_code' => $code,
                                        'sort_order'        => $i,
                                    ]);
                                    $updated++;
                                } else {
                                    CryptoCurrency::create([
                                        'now_payments_code' => $code,
                                        'symbol'            => $meta['symbol'],
                                        'name'              => $meta['name'],
                                        'network'           => $meta['network'],
                                        'is_active'         => true,
                                        'sort_order'        => $i,
                                    ]);
                                    $created++;
                                }
                            } catch (\Throwable $e) {
                                Log::error("CryptoCurrency sync failed for code [{$code}]", [
                                    'error' => $e->getMessage(),
                                    'meta'  => $meta,
                                ]);
                                $errors[] = $code;
                                $skipped++;
                            }
                        }

                        $body = "{$created} nueva(s), {$updated} actualizada(s).";
                        if ($skipped > 0) {
                            $body .= " {$skipped} error(es): " . implode(', ', $errors) . '. Revisa los logs.';
                        }

                        Notification::make()
                            ->title('Sincronización completada')
                            ->body($body)
                            ->success()
                            ->send();

                    } catch (\Throwable $e) {
                        Log::error('NowPayments sync failed', ['error' => $e->getMessage()]);
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
