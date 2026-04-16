<?php


namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CurrencyService
{
    protected $baseUrl = "https://api.exchangerate-api.com/v4/latest/";

    /*
    |--------------------------------------------------------------------------
    | CONVERT
    |--------------------------------------------------------------------------
    */
    public function convert($amount, $from, $to)
    {
        $rate = $this->getRate($from, $to);

        return $amount * $rate;
    }

    /*
    |--------------------------------------------------------------------------
    | GET RATE (avec cache 🔥)
    |--------------------------------------------------------------------------
    */
    public function getRate($from, $to)
    {
        $cacheKey = "rate_{$from}_{$to}";

        return Cache::remember($cacheKey, 3600, function () use ($from, $to) {

            $response = Http::get("{$this->baseUrl}{$from}");

            if (!$response->successful()) {
                throw new \Exception("Erreur API taux");
            }

            $data = $response->json();

            if (!isset($data['rates'][$to])) {
                throw new \Exception("Devise non supportée");
            }

            return $data['rates'][$to];
        });
    }
}
