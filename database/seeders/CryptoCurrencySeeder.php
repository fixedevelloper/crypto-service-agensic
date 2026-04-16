<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CryptoCurrency;

class CryptoCurrencySeeder extends Seeder
{
    public function run(): void
    {
        $cryptos = [
            [
                'name' => 'Tether USD',
                'code' => 'usdt', // En minuscule pour l'API
                'network' => 'trc20', // TRON -> trc20
                'icon' => 'https://cryptologos.cc/logos/tether-usdt-logo.png',
                'icon_res' => 'usdt_tron'
            ],
            [
                'name' => 'Tether USD',
                'code' => 'usdt',
                'network' => 'erc20', // ERC20 -> erc20
                'icon' => 'https://cryptologos.cc/logos/tether-usdt-logo.png',
                'icon_res' => 'usdt_eth'
            ],
            [
                'name' => 'Bitcoin',
                'code' => 'btc',
                'network' => '', // Le BTC n'a pas de suffixe réseau chez NOWPayments
                'icon' => 'https://cryptologos.cc/logos/bitcoin-btc-logo.png',
                'icon_res' => 'btc'
            ],
            [
                'name' => 'BNB',
                'code' => 'bnb',
                'network' => 'bsc', // BSC -> bsc
                'icon' => 'https://cryptologos.cc/logos/bnb-bnb-logo.png',
                'icon_res' => 'bnb'
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
