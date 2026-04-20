<?php


namespace App\Http\Controllers;

use App\Http\Helpers\Helpers;
use App\Http\Services\QuoteService;
use App\Models\CryptoCurrency;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    protected $quoteService;

    public function __construct(
        QuoteService $quoteService
    )
    {
        $this->quoteService = $quoteService;
    }

public function generate(Request $request)
{
    try {
        // 1. Validation des données
        $validated = $request->validate([
            'crypto' => 'required|string',
            'network' => 'required|string',
            'amount_xaf' => 'required|numeric|min:100'
        ]);

        // 2. Appel au service avec capture d'exception interne (ex: mapCurrency)
        $quote = $this->quoteService->createQuote(
            $request->crypto,
            $request->network,
            $request->amount_xaf
        );

        return response()->json([
            'status' => true,
            'message' => 'Cotation générée avec succès',
            'data' => $quote
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        // Capture les erreurs de validation (ex: montant trop bas)
        return response()->json([
            'status' => false,
            'message' => 'Erreur de validation',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        // Capture l'erreur "Currency not available" ou "Crypto non supportée"
        return response()->json([
            'status' => false,
            'message' => $e->getMessage() // Renvoie "Currency USDCERC20 is not available"
        ], 400);
    }
}

    public function calculateQuote(Request $request)
    {
        $amountXaf = $request->input('amount_xaf');
        $crypto = $request->input('crypto'); // ex: 'BTC'

        $rate = $this->getRate($crypto);

        if ($rate <= 0) {
            return response()->json(['error' => 'Impossible de récupérer le taux'], 500);
        }

        // Le bénéficiaire reçoit : (Montant / Taux)
        $totalCrypto = $amountXaf / $rate;

        return response()->json([
            'rate' => $rate,
            'total_crypto' => round($totalCrypto, 8),
        ]);
    }
    public function getCurrencyCrypto(Request $request){
        $cryptos=CryptoCurrency::where('is_active', true)->get();
        return Helpers::success($cryptos);
    }
}
