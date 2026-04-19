<?php


namespace App\Http\Controllers\Webhook;

use App\Jobs\RetryCreditUserJob;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NowPaymentsGatewayController extends BaseNowPaymentsIpnController
{
    public function __invoke(Request $request)
    {
        // 1. Validation de la signature (commune à tous)
        if (!$this->validateSignature($request)) {
        //    return response()->json(['message' => 'Invalid signature'], 400);
        }

        // 2. Aiguillage selon le contenu du payload
        if ($request->has('payment_id')) {
            return $this->handlePurchase($request);
        }

        if ($request->has('payout_id') || $request->has('id')) {
            return $this->handlePayout($request);
        }

        Log::info("IPN reçu de type inconnu", ['payload' => $request->all()]);
        return response()->json(['status' => 'unknown_type']);
    }

    private function handlePurchase(Request $request)
    {
        // Logique pour les paiements clients (ex: vente de crédits)
        $orderId = $request->order_id;
        return DB::transaction(function () use ($request) {
            $transaction = Payment::where('reference', $request->order_id)
                ->lockForUpdate()
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

    private function handlePayout(Request $request)
    {

        return DB::transaction(function () use ($request) {
            $transaction = Transaction::where('reference', $request->payout_id)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                return response()->json(['message' => 'Transaction not found'], 404);
            }

            switch ($request->status) {
                case 'FINISHED':
                    $this->processPayoutSuccess($transaction);
                    break;
                case 'FAILED':
                case 'EXPIRED':
                    if ($transaction->status === 'pending') {
                        $transaction->update(['status' => 'failed']);
                    }
                    break;
            }

            return response()->json(['status' => 'ok']);
        });
        return response()->json(['status' => 'payout_processed']);
    }


    private function processSuccess($transaction)
    {
        if ($transaction->status === 'success') return;

        try {
            // 1. Appel au microservice User
            // On envoie la 'reference' pour que le service User puisse vérifier l'idempotence
            $response = Http::withToken(config('services.user_service.token'))
                ->timeout(5) // On ne bloque pas le thread IPN trop longtemps
                ->post(config('services.user_service.url') . '/users-credit', [
                    'user_id'   => $transaction->user_id,
                    'amount'    => $transaction->fiat_amount,
                    'reference' => $transaction->reference, // Crucial !
                ]);

            if ($response->successful()) {
                // 2. Succès : On met à jour localement
                $transaction->update([
                    'status' => 'success',
                    'processed_at' => now()
                ]);
            } else {
                throw new \Exception("Le microservice User a renvoyé une erreur: " . $response->status());
            }

        } catch (\Exception $e) {
            Log::error("Échec crédit synchrone : " . $e->getMessage());

            // 3. Gestion d'échec : On marque comme 'pending_credit' et on lance un Job de secours
            $transaction->update(['status' => 'pending_credit']);

            // Ce Job réessaiera toutes les X minutes avec un "exponential backoff"
            RetryCreditUserJob::dispatch($transaction);
        }
    }
    private function processPayoutSuccess($transaction)
    {
        if ($transaction->status === 'success') return;

        try {
            // 1. Appel au microservice User
            // On envoie la 'reference' pour que le service User puisse vérifier l'idempotence
            $response = Http::withToken(config('services.user_service.token'))
                ->timeout(5) // On ne bloque pas le thread IPN trop longtemps
                ->post(config('services.user_service.url') . '/users-debit', [
                    'user_id'   => $transaction->user_id,
                    'amount'    => $transaction->amount_xaf,
                    'reference' => $transaction->reference, // Crucial !
                ]);

            if ($response->successful()) {
                // 2. Succès : On met à jour localement
                $transaction->update([
                    'status' => 'success',
                    'processed_at' => now()
                ]);
            } else {
                throw new \Exception("Le microservice User a renvoyé une erreur: " . $response->status());
            }

        } catch (\Exception $e) {
            Log::error("Échec crédit synchrone : " . $e->getMessage());

            // 3. Gestion d'échec : On marque comme 'pending_credit' et on lance un Job de secours
            $transaction->update(['status' => 'pending_credit']);

            // Ce Job réessaiera toutes les X minutes avec un "exponential backoff"
            RetryCreditUserJob::dispatch($transaction);
        }
    }
}
