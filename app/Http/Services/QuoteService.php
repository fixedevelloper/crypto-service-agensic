<?php


namespace App\Http\Services;

use App\Models\Quote;
use App\Models\FeeConfig;
use Carbon\Carbon;

class QuoteService
{
    protected $nowPayments;

    public function __construct(NowPaymentsService $nowPayments)
    {
        $this->nowPayments = $nowPayments;
    }

    public function createQuote($crypto, $network, $amountXaf)
    {
        // 1. Mapper la crypto
        $currencyTo = $this->mapCurrency($crypto, $network);

        // 2. Appel NOWPayments (estimate)
        $estimate = $this->nowPayments->estimate(
            $amountXaf,
            'xaf',
            strtolower($currencyTo)
        );

        if (!isset($estimate['estimated_amount'])) {
            throw new \Exception('Erreur estimation NOWPayments');
        }

        $amountCrypto = (float) $estimate['estimated_amount'];

        // 3. Récupérer frais config
        $feeConfig = FeeConfig::where('crypto', $crypto)
            ->where('network', $network)
            ->first();

        $percentFee = $feeConfig->percent_fee ?? 0;
        $fixedFee = $feeConfig->fixed_fee ?? 0;

        // 4. Calcul frais
        $fee = ($amountCrypto * $percentFee / 100) + $fixedFee;

        $totalCrypto = $amountCrypto + $fee;

        // 5. Rate calculé
        $rate = $amountCrypto / $amountXaf;

        // 6. Sauvegarde quote
        $quote = Quote::create([
            'crypto' => $crypto,
            'network' => $network,
            'amount_xaf' => $amountXaf,
            'rate' => $rate,
            'amount_crypto' => $amountCrypto,
            'fee' => $fee,
            'total_crypto' => $totalCrypto,
            'expires_at' => Carbon::now()->addMinutes(5)
        ]);

        return $quote;
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY QUOTE VALIDITY
    |--------------------------------------------------------------------------
    */
    public function validateQuote($quoteId)
    {
        $quote = Quote::findOrFail($quoteId);

        if (now()->gt($quote->expires_at)) {
            throw new \Exception('Quote expiré');
        }

        return $quote;
    }

    /*
    |--------------------------------------------------------------------------
    | CRYPTO MAPPING
    |--------------------------------------------------------------------------
    */
  private function mapCurrency($crypto, $network)
{
    $crypto = strtoupper(trim($crypto));
    $network = strtoupper(trim($network));

    return match ("$crypto-$network") {
        // --- USDT ---
        'USDT-TRON'    => 'usdttrc20',
        'USDT-ERC20'   => 'usdterc20',
        'USDT-BSC'     => 'usdtbep20',
        'USDT-BEP20'   => 'usdtbep20', // Alias fréquent

        // --- USDC ---
        'USDC-ERC20'   => 'usdcerc20',
        'USDC-BSC'     => 'usdcbep20',
        'USDC-BEP20'   => 'usdcbep20',
        'USDC-POLYGON' => 'usdcpoly',
        'USDC-SOLANA'  => 'usdcsol',

        // --- BTC & ETH ---
        'BTC-BTC'      => 'btc',
        'ETH-ERC20'    => 'eth',
        'ETH-BSC'      => 'ethbep20',

        // --- NATIVES & ALTCOINS ---
        'BNB-BSC'      => 'bnbbep20',
        'BNB-BEP20'    => 'bnbbep20',
        'SOL-SOLANA'   => 'sol',
        'POL-POLYGON'  => 'pol', 
        'MATIC-POLYGON'=> 'maticpoly', // Pour assurer la rétro-compatibilité

        default => throw new \Exception("Crypto ou réseau non supporté: $crypto sur le réseau $network")
    };

}
}
