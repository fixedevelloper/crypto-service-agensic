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
        $request->validate([
            'crypto' => 'required|string',
            'network' => 'required|string',
            'amount_xaf' => 'required|numeric|min:100'
        ]);
        $userId = $request->header('X-User-Id');
 /*       $quote = $this->quoteService->generate(
            $userId,
            $request->crypto,
            $request->network,
            $request->amount_xaf
        );*/
        $quote = $this->quoteService->createQuote(
            $request->crypto,
            $request->network,
            $request->amount_xaf
        );
        return response()->json([
            'status' => true,
            'data' => $quote
        ]);
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
