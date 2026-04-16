<?php


namespace App\Http\Services;

use App\Models\Quote;
use App\Models\FeeConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class QuoteService1
{
    public function generate($userId, $crypto, $network, $amountXaf)
    {
        $rate = $this->getRate($crypto);

        $amountCrypto = $amountXaf / $rate;

        $feeConfig = FeeConfig::where('crypto', $crypto)
            ->where('network', $network)
            ->where('is_active', true)
            ->first();

        $percentFee = $feeConfig->percent_fee ?? 2;
        $fixedFee = $feeConfig->fixed_fee ?? 0;

        $fee = ($amountCrypto * $percentFee / 100) + $fixedFee;

        $total = $amountCrypto - $fee;

        return Quote::create([
            'reference' => Str::uuid(),
            'user_id' => $userId,
            'crypto' => $crypto,
            'network' => $network,
            'amount_xaf' => $amountXaf,
            'rate' => $rate,
            'amount_crypto' => $amountCrypto,
            'fee' => $fee,
            'total_crypto' => $total,
            'expires_at' => now()->addMinutes(5)
        ]);
    }


    private function getRate($crypto)
    {
        $crypto = strtoupper($crypto);

        // On définit une clé unique pour chaque crypto (ex: rate_BTC)
        $cacheKey = "crypto_rate_{$crypto}";

        // On récupère ou on stocke pour 60 secondes (1 minute)
        return Cache::remember($cacheKey, 60, function () use ($crypto) {

            $map = [
                'USDT' => 'USDTEUR',
                'BTC'  => 'BTCEUR',
                'ETH'  => 'ETHEUR'
            ];

            if (!isset($map[$crypto])) {
                Log::warning("Crypto non supportée pour le taux : $crypto");
                return 0;
            }

            try {
                // Appel à Binance (Prix en Euro)
                $response = Http::timeout(5)->get('https://api.binance.com/api/v3/ticker/price', [
                    'symbol' => $map[$crypto]
                ]);

                if ($response->successful()) {
                    $priceInEur = (float) $response->json('price');

                    // Taux de conversion fixe XAF/EUR
                    $fixedXafRate = 655.957;

                    $priceInXaf = $priceInEur * $fixedXafRate;

                    Log::info("Taux mis à jour pour $crypto : $priceInXaf XAF");

                    return $priceInXaf;
                }

                Log::error("Échec API Binance pour $crypto : " . $response->status());
                return 0;

            } catch (\Exception $e) {
                Log::error("Erreur lors de la récupération du taux ($crypto) : " . $e->getMessage());
                return 0;
            }
        });
    }
/*    private function getRate($crypto)
    {
        $map = [
            'USDT' => 'tether',
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum'
        ];

        $response = Http::get(
            'https://api.binance.com/api/v3/ticker/price?symbol',
            [
                'ids' => $map[$crypto],
                'vs_currencies' => 'xaf'
            ]
        );
        logger($response);
        return $response[$map[$crypto]]['xaf'];
    }*/
}
