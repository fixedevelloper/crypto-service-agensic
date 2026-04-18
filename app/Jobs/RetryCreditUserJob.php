<?php

namespace App\Jobs;


use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RetryCreditUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Nombre de tentatives max.
     * On peut mettre un chiffre élevé car l'idempotence nous protège.
     */
    public $tries = 10;

    /**
     * Temps d'attente entre les tentatives (en secondes).
     * Ici : 10s, puis 30s, puis 1min, puis 2min...
     */
    public function backoff()
    {
        return [10, 30, 60, 120, 300];
    }

    protected $transaction;

    public function __construct(Payment $transaction)
    {
        $this->transaction = $transaction;
    }

    public function handle()
    {
        // Si la transaction a déjà été validée entre-temps, on arrête.
        if ($this->transaction->status === 'success') {
            return;
        }

        $response = Http::withToken(config('services.user_service.token'))
            ->post(config('services.user_service.url') . '/users-credit', [
                'user_id'   => $this->transaction->user_id,
                'amount'    => $this->transaction->fiat_amount,
                'reference' => $this->transaction->reference,
            ]);

        if ($response->successful()) {
            $this->transaction->update([
                'status' => 'success',
                'processed_at' => now()
            ]);

            Log::info("Crédit réussi via Job pour : " . $this->transaction->reference);
        } else {
            // Si le code est 4xx (erreur client), inutile de retenter 10 fois
            if ($response->clientError()) {
                Log::error("Erreur critique (4xx) sur le service User : " . $response->body());
                $this->fail(new \Exception("Erreur client non récupérable"));
                return;
            }

            // Pour les 5xx ou erreurs réseau, le Job sera automatiquement
            // remis en file d'attente grâce au throw
            throw new \Exception("Microservice User indisponible, nouvelle tentative...");
        }
    }
}
