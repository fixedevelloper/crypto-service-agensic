<?php


namespace App\Http\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NowPaymentsService
{
    private $baseUrl;
    private $apiKey;
    private $email;
    private $password;

    public function __construct()
    {
        $this->baseUrl = config('services.nowpayments.base_url');
        $this->apiKey = config('services.nowpayments.api_key');
        $this->email = config('services.nowpayments.email');
        $this->password = config('services.nowpayments.password');
    }

    private function headers()
    {
        return [
            'Authorization' => 'Bearer ' . $this->getAuthToken(),
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ];
    }

    public function auth()
    {
        $response = Http::timeout(10)
            ->post("{$this->baseUrl}/auth", [
                'email' => $this->email,
                'password' => $this->password,
            ]);

        $data = $response->json();
        logger($this->email);
        logger($data);
        if (!$response->successful() || !isset($data['token'])) {
            throw new \Exception(
                'NOWPayments auth failed: ' . json_encode($data)
            );
        }

        return $data['token'];
    }

    public function getAuthToken()
    {
        return Cache::remember('nowpayments_token', 300, function () {
            return $this->auth();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | GET MIN AMOUNT (OPTIONNEL)
    |--------------------------------------------------------------------------
    */
    public function getMinAmount($currencyFrom, $currencyTo)
    {
        return Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/min-amount", [
                'currency_from' => $currencyFrom,
                'currency_to' => $currencyTo
            ])
            ->json();
    }

    /*
    |--------------------------------------------------------------------------
    | ESTIMATE (QUOTE 🔥)
    |--------------------------------------------------------------------------
    */
    public function estimate($amount, $currencyFrom, $currencyTo)
    {
/*        $currencyService= app(CurrencyService::class);
        $amountUsd = $currencyService->convert(
            $amount,
            'XAF',
            'USD'
        );
        logger($amountUsd);*/

        $amountUsd = $amount / 600; // approx ou via API forex
        return Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/estimate", [
                'amount' => $amountUsd,
                'currency_from' => 'usd',
                'currency_to' => $currencyTo
            ])
            ->json();
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE PAYMENT (DEPOSIT)
    |--------------------------------------------------------------------------
    */
    public function createPayment($amount, $currency, $payCurrency, $orderId)
    {
        // On convertit tout en USD pour NOWPayments
        $amountInUsd = $this->convertToUsd($amount, strtoupper($currency));

        return Http::withHeaders($this->headers())->post("{$this->baseUrl}/payment", [
            'price_amount' => $amountInUsd,
            'price_currency' => 'usd', // Devise pivot
            'pay_currency' => $payCurrency,
            'order_id' => $orderId,
            'ipn_callback_url' => route('nowpayments.ipn')
        ])->json();
    }

    private function convertToUsd($amount, $fromCurrency)
    {
        // Ici, tu peux utiliser une API de change (ex: Fixer.io)
        // ou des taux fixes si tu veux garder une marge.
        $rates = [
            'XAF' => 655,
            'XOF' => 655,
            'NGN' => 1500,
            'USD' => 1
        ];

        $rate = $rates[$fromCurrency] ?? 655;
        return $amount / $rate;
    }

    /*
    |--------------------------------------------------------------------------
    | PAYOUT (SEND CRYPTO 🔥🔥🔥)
    |--------------------------------------------------------------------------
    */
    public function payout($address, $amount, $currency)
    {
        return Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/payout", [
                'withdrawals' => [
                    [
                        'address' => $address,
                        'currency' => $currency, // USDTTRC20
                        'amount' => $amount,
                        'ipn_callback_url' => route('nowpayments.ipn')
                    ]
                ]
            ])
            ->json();
    }
}
