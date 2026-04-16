<?php


namespace App\Http\Services;

use App\Models\Transaction;
use App\Models\TransferLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookService
{
/*    public function handle(Request $request)
    {
        // 🔐 1. Vérifier signature
        $this->verifySignature($request);

        $payload = $request->all();

        // Exemple TronGrid payload
        $txHash = $payload['transaction_id'] ?? null;
        $status = $payload['status'] ?? 'pending';

        if (!$txHash) {
            Log::warning('Webhook sans tx_hash', $payload);
            return;
        }

        // 🔍 2. retrouver transaction
        $transaction = Transaction::where('tx_hash', $txHash)->first();

        if (!$transaction) {
            Log::warning('Transaction non trouvée', ['tx_hash' => $txHash]);
            return;
        }

        // 🔁 3. idempotence (éviter double traitement)
        if ($transaction->status === 'success') {
            return;
        }

        // 🔄 4. mapping status
        $mappedStatus = $this->mapStatus($status);

        // 🔥 5. update transaction
        $transaction->update([
            'status' => $mappedStatus,
            'provider_response' => $payload,
            'processed_at' => now()
        ]);

        // 🧾 6. log
        $this->log($transaction->id, $mappedStatus, 'Webhook reçu', $payload);
    }*/

    public function handle(Request $request)
    {
        $payload = $request->all();

        $paymentId = $payload['payout_id'] ?? null;
        $status = $payload['status'] ?? null;

        $transaction = Transaction::where('tx_hash', $paymentId)->first();

        if (!$transaction) return response()->json(['ok' => true]);

        if ($status === 'finished') {
            $transaction->update(['status' => 'success']);
        } elseif (in_array($status, ['failed', 'expired'])) {
            $transaction->update(['status' => 'failed']);
        } else {
            $transaction->update(['status' => 'processing']);
        }

        return response()->json(['ok' => true]);
    }
    private function mapStatus($status)
    {
        return match ($status) {
        'CONFIRMED', 'SUCCESS' => 'success',
            'FAILED' => 'failed',
            default => 'processing'
        };
    }

    private function log($transactionId, $status, $message, $data = null)
    {
        TransferLog::create([
            'transaction_id' => $transactionId,
            'status' => $status,
            'message' => $message,
            'data' => $data
        ]);
    }

    private function verifySignature(Request $request)
    {
        $signature = $request->header('X-Signature');

        if (!$signature) {
            abort(403, 'Signature manquante');
        }

        $expected = hash_hmac(
            'sha256',
            $request->getContent(),
            env('WEBHOOK_SECRET')
        );

        if (!hash_equals($expected, $signature)) {
            abort(403, 'Signature invalide');
        }
    }
}
