<?php

declare(strict_types=1);

namespace App\Filament\Resources\CryptoCurrencyResource\Pages;

use App\Contracts\CryptoProviderInterface;
use App\Filament\Resources\CryptoCurrencyResource;
use App\Models\CryptoCurrency;
use Filament\Actions\Action;
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
                ->modalDescription('Se activarán las monedas que existan en NowPayments y se desactivarán las que ya no estén.')
                ->action(function (): void {
                    try {
                        $provider = app(CryptoProviderInterface::class);
                        $codes    = $provider->getMerchantCurrencies();

                        Log::info('NowPayments getMerchantCurrencies raw codes', ['codes' => $codes]);

                        if (empty($codes)) {
                            Notification::make()
                                ->title('Sin monedas')
                                ->body('NowPayments devolvió una lista vacía. Verifica tu cuenta.')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Normalize all codes to lowercase
                        $normalizedCodes = array_map('strtolower', $codes);

                        // Map from NowPayments code to display metadata
                        $knownMeta = [
                            'btc'          => ['symbol' => 'BTC',       'name' => 'Bitcoin',          'network' => 'Bitcoin'],
                            'btg'          => ['symbol' => 'BTG',       'name' => 'Bitcoin Gold',      'network' => 'Bitcoin Gold'],
                            'bch'          => ['symbol' => 'BCH',       'name' => 'Bitcoin Cash',      'network' => 'Bitcoin Cash'],
                            'bcd'          => ['symbol' => 'BCD',       'name' => 'Bitcoin Diamond',   'network' => 'Bitcoin Diamond'],
                            'eth'          => ['symbol' => 'ETH',       'name' => 'Ethereum',          'network' => 'ERC20'],
                            'usdttrc20'    => ['symbol' => 'USDT',      'name' => 'Tether (TRC20)',    'network' => 'TRC20'],
                            'usdterc20'    => ['symbol' => 'USDT-ERC20','name' => 'Tether (ERC20)',    'network' => 'ERC20'],
                            'usdt'         => ['symbol' => 'USDT-OMNI', 'name' => 'Tether (Omni)',     'network' => 'Omni'],
                            'usdc'         => ['symbol' => 'USDC',      'name' => 'USD Coin',          'network' => 'ERC20'],
                            'ltc'          => ['symbol' => 'LTC',       'name' => 'Litecoin',          'network' => 'Litecoin'],
                            'trx'          => ['symbol' => 'TRX',       'name' => 'TRON',              'network' => 'TRC20'],
                            'bnb'          => ['symbol' => 'BNB',       'name' => 'BNB',               'network' => 'BEP2'],
                            'bnbmainnet'   => ['symbol' => 'BNB-BSC',   'name' => 'BNB (BSC)',         'network' => 'BSC'],
                            'busd'         => ['symbol' => 'BUSD',      'name' => 'Binance USD',       'network' => 'BSC'],
                            'xrp'          => ['symbol' => 'XRP',       'name' => 'Ripple',            'network' => 'XRP'],
                            'xlm'          => ['symbol' => 'XLM',       'name' => 'Stellar',           'network' => 'Stellar'],
                            'doge'         => ['symbol' => 'DOGE',      'name' => 'Dogecoin',          'network' => 'Dogecoin'],
                            'ada'          => ['symbol' => 'ADA',       'name' => 'Cardano',           'network' => 'Cardano'],
                            'sol'          => ['symbol' => 'SOL',       'name' => 'Solana',            'network' => 'Solana'],
                            'dot'          => ['symbol' => 'DOT',       'name' => 'Polkadot',          'network' => 'Polkadot'],
                            'matic'        => ['symbol' => 'MATIC',     'name' => 'Polygon',           'network' => 'Polygon'],
                            'link'         => ['symbol' => 'LINK',      'name' => 'Chainlink',         'network' => 'ERC20'],
                            'uni'          => ['symbol' => 'UNI',       'name' => 'Uniswap',           'network' => 'ERC20'],
                            'dai'          => ['symbol' => 'DAI',       'name' => 'Dai',               'network' => 'ERC20'],
                            'ltc'          => ['symbol' => 'LTC',       'name' => 'Litecoin',          'network' => 'Litecoin'],
                            'xmr'          => ['symbol' => 'XMR',       'name' => 'Monero',            'network' => 'Monero'],
                            'zec'          => ['symbol' => 'ZEC',       'name' => 'Zcash',             'network' => 'Zcash'],
                            'xvg'          => ['symbol' => 'XVG',       'name' => 'Verge',             'network' => 'Verge'],
                            'qtum'         => ['symbol' => 'QTUM',      'name' => 'Qtum',              'network' => 'Qtum'],
                            'dash'         => ['symbol' => 'DASH',      'name' => 'Dash',              'network' => 'Dash'],
                            'xem'          => ['symbol' => 'XEM',       'name' => 'NEM',               'network' => 'NEM'],
                            'dgb'          => ['symbol' => 'DGB',       'name' => 'DigiByte',          'network' => 'DigiByte'],
                            'lsk'          => ['symbol' => 'LSK',       'name' => 'Lisk',              'network' => 'Lisk'],
                            'kmd'          => ['symbol' => 'KMD',       'name' => 'Komodo',            'network' => 'Komodo'],
                            'rep'          => ['symbol' => 'REP',       'name' => 'Augur',             'network' => 'ERC20'],
                            'bat'          => ['symbol' => 'BAT',       'name' => 'Basic Attention',   'network' => 'ERC20'],
                            'ark'          => ['symbol' => 'ARK',       'name' => 'Ark',               'network' => 'Ark'],
                            'waves'        => ['symbol' => 'WAVES',     'name' => 'Waves',             'network' => 'Waves'],
                            'xzc'          => ['symbol' => 'XZC',       'name' => 'Firo',              'network' => 'Firo'],
                            'nano'         => ['symbol' => 'NANO',      'name' => 'Nano',              'network' => 'Nano'],
                            'tusd'         => ['symbol' => 'TUSD',      'name' => 'TrueUSD',           'network' => 'ERC20'],
                            'vet'          => ['symbol' => 'VET',       'name' => 'VeChain',           'network' => 'VeChain'],
                            'zen'          => ['symbol' => 'ZEN',       'name' => 'Horizen',           'network' => 'Horizen'],
                            'grs'          => ['symbol' => 'GRS',       'name' => 'Groestlcoin',       'network' => 'Groestlcoin'],
                            'fun'          => ['symbol' => 'FUN',       'name' => 'FunToken',          'network' => 'ERC20'],
                            'neo'          => ['symbol' => 'NEO',       'name' => 'NEO',               'network' => 'NEO'],
                            'gas'          => ['symbol' => 'GAS',       'name' => 'GAS',               'network' => 'NEO'],
                            'pax'          => ['symbol' => 'PAX',       'name' => 'Pax Dollar',        'network' => 'ERC20'],
                            'ont'          => ['symbol' => 'ONT',       'name' => 'Ontology',          'network' => 'Ontology'],
                            'xtz'          => ['symbol' => 'XTZ',       'name' => 'Tezos',             'network' => 'Tezos'],
                            'rvn'          => ['symbol' => 'RVN',       'name' => 'Ravencoin',         'network' => 'Ravencoin'],
                            'zil'          => ['symbol' => 'ZIL',       'name' => 'Zilliqa',           'network' => 'Zilliqa'],
                            'cro'          => ['symbol' => 'CRO',       'name' => 'Cronos',            'network' => 'ERC20'],
                            'ht'           => ['symbol' => 'HT',        'name' => 'Huobi Token',       'network' => 'ERC20'],
                            'wabi'         => ['symbol' => 'WABI',      'name' => 'Wabi',              'network' => 'ERC20'],
                            'algo'         => ['symbol' => 'ALGO',      'name' => 'Algorand',          'network' => 'Algorand'],
                            'gt'           => ['symbol' => 'GT',        'name' => 'GateToken',         'network' => 'ERC20'],
                            'stpt'         => ['symbol' => 'STPT',      'name' => 'Standard Tokenization', 'network' => 'ERC20'],
                            'ava'          => ['symbol' => 'AVA',       'name' => 'Travala',           'network' => 'ERC20'],
                            'sxp'          => ['symbol' => 'SXP',       'name' => 'Solar',             'network' => 'ERC20'],
                            'okb'          => ['symbol' => 'OKB',       'name' => 'OKB',               'network' => 'ERC20'],
                        ];

                        $created    = 0;
                        $activated  = 0;
                        $deactivated = 0;
                        $skipped    = 0;
                        $errors     = [];

                        // Upsert each currency from NowPayments
                        foreach ($normalizedCodes as $i => $code) {
                            $meta = $knownMeta[$code] ?? [
                                'symbol'  => strtoupper($code),
                                'name'    => strtoupper($code),
                                'network' => null,
                            ];

                            try {
                                $existing = CryptoCurrency::where('now_payments_code', $code)->first();

                                if ($existing) {
                                    $wasInactive = ! $existing->is_active;
                                    $existing->update([
                                        'is_active'  => true,
                                        'sort_order' => $i,
                                    ]);
                                    if ($wasInactive) $activated++;
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
                                Log::error("CryptoCurrency sync failed for [{$code}]", ['error' => $e->getMessage()]);
                                $errors[] = $code;
                                $skipped++;
                            }
                        }

                        // Deactivate currencies no longer in NowPayments
                        $deactivated = CryptoCurrency::whereNotNull('now_payments_code')
                            ->whereNotIn('now_payments_code', $normalizedCodes)
                            ->where('is_active', true)
                            ->update(['is_active' => false]);

                        $body = "{$created} nueva(s), {$activated} reactivada(s), {$deactivated} desactivada(s).";
                        if ($skipped > 0) {
                            $body .= " {$skipped} error(es): " . implode(', ', $errors) . '. Ver logs.';
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
        ];
    }
}
