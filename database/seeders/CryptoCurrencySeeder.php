<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CryptoCurrency;

class CryptoCurrencySeeder extends Seeder
{
    public function run(): void
    {
        $cryptos = [
            // --- TETHER (USDT) ---
            [
                'name' => 'Tether USD',
                'code' => 'usdt',
                'network' => 'trc20',
                'icon' => 'https://cryptologos.cc/logos/tether-usdt-logo.png',
                'icon_res' => 'usdt_tron'
            ],
            [
                'name' => 'Tether USD',
                'code' => 'usdt',
                'network' => 'erc20',
                'icon' => 'https://cryptologos.cc/logos/tether-usdt-logo.png',
                'icon_res' => 'usdt_eth'
            ],
            [
                'name' => 'Tether USD',
                'code' => 'usdt',
                'network' => 'bep20', // Binance Smart Chain
                'icon' => 'https://cryptologos.cc/logos/tether-usdt-logo.png',
                'icon_res' => 'usdt_bsc'
            ],

            // --- USD COIN (USDC) ---
            [
                'name' => 'USD Coin',
                'code' => 'usdc',
                'network' => 'erc20',
                'icon' => 'https://cryptologos.cc/logos/usd-coin-usdc-logo.png',
                'icon_res' => 'usdc_eth'
            ],
            [
                'name' => 'USD Coin',
                'code' => 'usdc',
                'network' => 'trc20',
                'icon' => 'https://cryptologos.cc/logos/usd-coin-usdc-logo.png',
                'icon_res' => 'usdc_tron'
            ],
            
[
    'name' => 'USD Coin',
    'code' => 'usdc',
    'network' => 'bsc', // On utilise 'bsc' ici pour plus de clarté
    'icon' => 'https://cryptologos.cc/logos/usd-coin-usdc-logo.png',
    'icon_res' => 'usdc_bsc'
],
            [
                'name' => 'USD Coin',
                'code' => 'usdc',
                'network' => 'matic', // Polygon
                'icon' => 'https://cryptologos.cc/logos/usd-coin-usdc-logo.png',
                'icon_res' => 'usdc_polygon'
            ],

            // --- MAJEURES ---
            [
                'name' => 'Bitcoin',
                'code' => 'btc',
                'network' => '',
                'icon' => 'https://cryptologos.cc/logos/bitcoin-btc-logo.png',
                'icon_res' => 'btc'
            ],
            [
                'name' => 'Ethereum',
                'code' => 'eth',
                'network' => 'eth',
                'icon' => 'https://cryptologos.cc/logos/ethereum-eth-logo.png',
                'icon_res' => 'eth'
            ],
            [
                'name' => 'Solana',
                'code' => 'sol',
                'network' => 'sol',
                'icon' => 'https://cryptologos.cc/logos/solana-sol-logo.png',
                'icon_res' => 'sol'
            ],
            [
                'name' => 'Litecoin',
                'code' => 'ltc',
                'network' => '',
                'icon' => 'https://cryptologos.cc/logos/litecoin-ltc-logo.png',
                'icon_res' => 'ltc'
            ],
            [
                'name' => 'Tron',
                'code' => 'trx',
                'network' => 'trx',
                'icon' => 'https://cryptologos.cc/logos/tron-trx-logo.png',
                'icon_res' => 'trx'
            ],
            [
                'name' => 'Dogecoin',
                'code' => 'doge',
                'network' => '',
                'icon' => 'https://cryptologos.cc/logos/dogecoin-doge-logo.png',
                'icon_res' => 'doge'
            ],
            [
    'name' => 'Polygon',
    'code' => 'pol',
    'network' => 'polygon',
    'icon' => 'https://cryptologos.cc/logos/polygon-matic-logo.png',
    'icon_res' => 'pol_matic'
],
        ];

        foreach ($cryptos as $crypto) {
            CryptoCurrency::updateOrCreate(
                ['code' => $crypto['code'], 'network' => $crypto['network']],
                $crypto
            );
        }
    }
}
