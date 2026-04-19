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

        if ($currency === 'usdt' || $currency === 'usdc') {
            $formattedCurrency = match ($network) {
            'trc20', 'tron' => $currency . 'trc20',
            'erc20', 'eth'  => $currency . 'erc20',
            'bsc', 'bep20'  => $currency . 'bep20',
            'matic', 'polygon' => $currency . 'polygon',
            default => $currency . 'trc20', // On définit un vrai défaut technique
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
