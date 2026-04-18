<?php


namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
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
    private function processSuccess($transaction)
    {
        // 1. Vérification de sécurité pour éviter les doubles traitements
        if ($transaction->status === 'success') {
            return;
        }

        try {
            DB::transaction(function () use ($transaction) {
                // 2. Mise à jour du statut de la transaction
                $transaction->update([
                    'status' => 'success',
                    'processed_at' => now()
                ]);

                // 3. Action métier (Exemple : Créditer le compte de l'utilisateur)
                $user = $transaction->user;
                $user->increment('balance', $transaction->amount);

                // 4. (Optionnel) Enregistrement dans un historique de solde
                // BalanceHistory::create([...]);

                Log::info("Paiement validé et traité pour la transaction: " . $transaction->reference);
            });

            // 5. Actions hors transaction (Emails, Notifications Push)
            // On le fait après le commit DB pour éviter d'envoyer un mail si la DB crash
            // Mail::to($transaction->user)->send(new PaymentReceived($transaction));

        } catch (\Exception $e) {
            Log::error("Erreur lors du traitement processSuccess: " . $e->getMessage());
            // Optionnel : Alerter l'admin que le paiement est reçu mais le service non rendu
        }
    }
}
