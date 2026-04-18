<?php

namespace App\Http\Services\microService;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserServiceClient
{

    // app/Services/UserServiceClient.php

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
}
