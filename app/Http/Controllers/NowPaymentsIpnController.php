<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class NowPaymentsIpnController extends Controller
{

    public function __invoke(Request $request)
    {
        $ipnSecret = config('services.nowpayments.ipn_secret');
        $receivedSignature = $request->header('x-nowpayments-sig');

        // 1. Récupération et tri des données
        $payload = $request->all();
        if (empty($payload)) {
            return response()->json(['message' => 'Empty payload'], 400);
        }

        ksort($payload);

        // IMPORTANT: NOWPayments attend un JSON sans espaces après les virgules/deux-points
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // 2. Calcul et comparaison sécurisée
        $calculatedSignature = hash_hmac('sha512', $jsonPayload, $ipnSecret);

        if (!$receivedSignature || !hash_equals($calculatedSignature, $receivedSignature)) {
            Log::error("IPN Signature invalide !", [
                'expected' => $calculatedSignature,
                'received' => $receivedSignature
            ]);
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // 3. Traitement atomique
        return DB::transaction(function () use ($request) {
            $transaction = Transaction::where('reference', $request->order_id)
                ->lockForUpdate() // Évite les conditions de concurrence
                ->first();

            if (!$transaction) {
                return response()->json(['message' => 'Transaction not found'], 404);
            }

            switch ($request->payment_status) {
                case 'finished':
                    $this->processSuccess($transaction);
                    break;
                case 'failed':
                case 'expired':
                    if ($transaction->status === 'pending') {
                        $transaction->update(['status' => 'failed']);
                    }
                    break;
            }

            return response()->json(['status' => 'ok']);
        });
    }
}
