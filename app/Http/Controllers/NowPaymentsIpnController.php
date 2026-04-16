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

        // 1. Récupérer les données brutes et les trier (requis pour la signature)
        $payload = $request->all();
        ksort($payload);
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // 2. Calculer la signature locale (Hmac SHA512)
        $calculatedSignature = hash_hmac('sha512', $jsonPayload, $ipnSecret);

        // 3. Comparaison sécurisée
        if ($receivedSignature !== $calculatedSignature) {
            Log::warning("IPN Signature invalide !", ['received' => $receivedSignature]);
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // 4. Traitement selon le statut
        $paymentStatus = $request->payment_status;
        $orderId = $request->order_id;

        $transaction = Transaction::where('reference', $orderId)->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        switch ($paymentStatus) {
            case 'finished':
                $this->processSuccess($transaction);
                break;
            case 'failed':
            case 'expired':
                $transaction->update(['status' => 'failed']);
                break;
        }

        return response()->json(['status' => 'ok']);
    }

    private function processSuccess($transaction) {
        if ($transaction->status !== 'success') {
            $transaction->update(['status' => 'success', 'processed_at' => now()]);
            // Déclencher ici ton service de Payout ou l'envoi de mail
        }
    }
}
