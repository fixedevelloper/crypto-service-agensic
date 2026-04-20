<?php


namespace App\Http\Services;


use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AddressValidatorService
{
    private $apiKey;
    private $baseUrl = 'https://api.nowpayments.io/v1/payout/validate-address';

    public function __construct()
    {
        $this->apiKey = config('services.nowpayments.api_key');
    }

    /**
     * Valide une adresse via l'API NOWPayments
     * @param string $currency
     * @param string $address
     * @param string|null $network
     * @param string|null $extraId
     * @return bool
     */
    public function validate(string $currency, string $address, ?string $network = null, ?string $extraId = null): bool
    {
        // 1. On nettoie et on met en minuscule
        $currency = strtolower($currency);
        $network = $network ? strtolower($network) : '';

        // 2. Mapping de correction pour les réseaux
        // NOWPayments attend 'usdttrc20' et non 'usdt trc20' ou 'usdt default'
        $formattedCurrency = $currency;
// On s'assure que tout est en minuscule pour un matching parfait
$currency = strtolower($currency);
$network = strtolower($network);

if ($currency === 'usdt' || $currency === 'usdc') {
    $formattedCurrency = match ($network) {
        'trc20', 'tron'            => $currency . 'trc20',
        'erc20', 'eth', 'ethereum' => $currency . 'erc20',
        'bsc', 'bep20'             => $currency . 'bsc', // ou 'usdcbep20' selon votre API
        'polygon', 'matic', 'pol'  => $currency . 'polygon',
        'solana', 'sol'            => $currency . 'sol',
        
        // Défaut sécurisé : on force le TRC20 (souvent le moins cher pour l'USDT)
        // ou on lance une exception si on veut être strict
        default => $currency . 'trc20', 
    };
} else {
    // Pour BTC, ETH, SOL, POL qui n'ont généralement pas besoin de suffixe réseau
    // ou qui ont un mapping simple.
    $formattedCurrency = match ($currency) {
        'btc'    => 'btc',
        'eth'    => ($network === 'bsc' || $network === 'bep20') ? 'ethbsc' : 'eth',
        'sol'    => 'sol',
        'matic', 'pol' => 'maticpoly',
        'bnb'    => 'bnb',
        default  => $currency,
    };
}


        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl, [
                'address'  => $address,
                'currency' => $formattedCurrency,
                'extra_id' => $extraId,
            ]);

            logger("Contenu brut reçu : " . $response->body());

            if ($response->successful()) {
                // Validation si la réponse est exactement "OK" (ignorant la casse et espaces)
                return trim(strtoupper($response->body())) === 'OK';
            }

            Log::error("Erreur API Validation Adresse", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error("Exception lors de la validation : " . $e->getMessage());
            return false;
        }
    }
}
