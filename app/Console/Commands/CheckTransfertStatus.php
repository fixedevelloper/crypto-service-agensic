<?php

namespace App\Console\Commands;

use App\Http\Services\microService\UserServiceClient;
use App\Models\Payment;
use Illuminate\Console\Command;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckTransfertStatus extends Command
{
    // Utilisation : php artisan payment:check {payment_id?}
    protected $signature = 'transfer:check';
    protected $description = 'Récupère le statut d\'un transferts depuis NOWPayments et met à jour la base de données';

    public function handle()
    {
        // 1. On récupère uniquement les paiements qui ont besoin d'être vérifiés
        $payments = Transaction::query()
            ->where('status', 'processing')
            ->where('created_at', '>', now()->subDay()) // Sécurité : on ne remonte pas à l'infini
            ->get();

        if ($payments->isEmpty()) {
            $this->info("Aucun paiement en cours de traitement.");
            return;
        }

        $this->info("Vérification de " . $payments->count() . " paiements...");

        foreach ($payments as $payment) {

            $identifier = $payment->provider_id ?? $payment->reference;

            if ($identifier) {
                $this->line("Vérification pour l'ID : " . $identifier);
                $this->processPayment($identifier);
            } else {
                $this->error("Paiement ID #{$payment->id} n'a ni provider_id ni référence.");
            }
        }

        $this->info("Vérification terminée.");
    }

    private function processPayment($paymentId)
    {
        $this->line("Vérification du paiement : $paymentId");

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.nowpayments.api_key'),
            ])->get("https://api.nowpayments.io/v1/payout/$paymentId");

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['payment_status'];

                $this->updateTransaction($paymentId, $data);
                $this->info("Statut actuel : $status");
            } else {
                $this->error("Erreur API pour $paymentId : " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("Erreur : " . $e->getMessage());
        }
    }

    private function updateTransaction($paymentId, $data)
    {
        $transaction = Transaction::where('provider_id', $paymentId)->first();

        if (!$transaction) return;

        // Si le paiement est fini, on déclenche la logique de succès
        if ($data['payment_status'] === 'finished' && $transaction->status !== 'success') {
            $transaction->update([
                'status' => 'success',
                'tx_hash' => $data['hash'] ?? $transaction->tx_hash,
                'processed_at' => now(),
            ]);

            // Ici, tu peux appeler ton service pour créditer l'utilisateur
             app(UserServiceClient::class)->credit($transaction);

            $this->info("Transaction $paymentId marquée comme SUCCESS");
        }
        elseif (in_array($data['payment_status'], ['failed', 'expired'])) {
            $transaction->update(['status' => 'failed']);
            $this->warn("Transaction $paymentId marquée comme FAILED");
        }
    }
}
