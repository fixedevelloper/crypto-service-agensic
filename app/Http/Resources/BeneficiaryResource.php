<?php


namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeneficiaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'phone' => $this->phone,

            // Détails du portefeuille crypto du bénéficiaire
            'wallet' => [
                'crypto'  => strtoupper($this->crypto),
                'network' => strtoupper($this->network),
                'address' => $this->recipient_address,
                'short_address' => $this->getShortAddress(),
            ],

            // Données du propriétaire (Microservice User)
            // On injecte manuellement les données récupérées depuis le service User
            'owner' => $this->user_data ?? [
                    'id' => $this->user_id,
                    'status' => 'external'
                ],

            // Statistiques (optionnel : si vous voulez voir l'activité du bénéficiaire)
            'stats' => [
                'transactions_count' => $this->whenCounted('transactions'),
            ],

            'created_at' => $this->created_at->format('d/m/Y H:i'),
        ];
    }

    /**
     * Formate l'adresse pour l'affichage UI (ex: 0x123...4567)
     */
    private function getShortAddress(): string
    {
        $addr = $this->recipient_address;
        if (strlen($addr) < 10) return $addr;
        return substr($addr, 0, 6) . '...' . substr($addr, -4);
    }
}
