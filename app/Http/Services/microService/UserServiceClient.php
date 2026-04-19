<?php

namespace App\Http\Services\microService;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserServiceClient
{


    public function getUsersByIds(array $ids)
    {
        if (empty($ids)) return [];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('API_SERVICE_TOKEN'),
            ])->get(env('USER_SERVICE_URL') . '/api/users', [
                'ids' => implode(',', $ids)
            ]);

            if ($response->successful()) {
                // On indexe par ID pour un accès rapide : [1 => [...], 2 => [...]]
                return collect($response->json('data'))->keyBy('id')->toArray();
            }
        } catch (\Exception $e) {
            Log::error("Échec de récupération des utilisateurs : " . $e->getMessage());
        }

        return [];
    }
    // app/Services/UserServiceClient.php

    public function getUserById($userId)
    {
        // On peut ajouter un cache court pour éviter de surcharger le réseau
        return Cache::remember("user_detail_{$userId}", 60, function () use ($userId) {
            $response = Http::withToken(env('API_SERVICE_TOKEN'))
                ->get(env('USER_SERVICE_URL') . "/api/users/{$userId}");

            return $response->successful() ? $response->json('data') : null;
        });
    }
    public function credit($transaction)
    {
        // Si la transaction a déjà été validée entre-temps, on arrête.
        if ($transaction->status === 'success') {
            return;
        }

        $response = Http::withToken(config('services.user_service.token'))
            ->post(config('services.user_service.url') . '/users-credit', [
                'user_id'   => $transaction->user_id,
                'amount'    => $transaction->fiat_amount,
                'reference' => $transaction->reference,
            ]);

        if ($response->successful()) {
            $transaction->update([
                'status' => 'success',
                'processed_at' => now()
            ]);

            Log::info("Crédit réussi via  pour : " . $transaction->reference);
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
    public function debit($transaction)
    {
        // Si la transaction a déjà été validée entre-temps, on arrête.
        if ($transaction->status === 'success') {
            return;
        }

        $response = Http::withToken(config('services.user_service.token'))
            ->post(config('services.user_service.url') . '/users-debit', [
                'user_id'   => $transaction->user_id,
                'amount'    => $transaction->fiat_amount,
                'reference' => $transaction->reference,
            ]);

        if ($response->successful()) {
            $transaction->update([
                'status' => 'success',
                'processed_at' => now()
            ]);

            Log::info("Crédit réussi via  pour : " . $transaction->reference);
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
