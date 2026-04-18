<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'reference'         => $this->reference,

            // Montants formatés
            'fiat' => [
                'amount'   => (float) $this->fiat_amount,
                'currency' => $this->fiat_currency,
                'display'  => number_format($this->fiat_amount, 2) . ' ' . $this->fiat_currency,
            ],

            'crypto' => [
                'amount'   => (float) $this->crypto_amount,
                'currency' => $this->crypto_currency,
                'display'  => $this->crypto_amount . ' ' . $this->crypto_currency,
                'pay_address' => $this->pay_address,
                'recipient'   => $this->recipient_address,
            ],

            // Statut et visuels
            'status' => [
                'value' => $this->status,
                'label' => $this->getStatusLabel(),
                'color' => $this->status_color, // Utilise ton accesseur getStatusColorAttribute
            ],

            // Dates
            'processed_at' => $this->processed_at ? $this->processed_at->format('d/m/Y H:i') : null,
            'created_at'   => $this->created_at->format('d/m/Y H:i'),

            // Relations (chargées à la demande)
            'user' => $this->user_data ?? [
                    'id' => $this->user_id,
                    'name' => 'Utilisateur #' . $this->user_id,
                    'is_external' => true
                ],

            // Meta pour le frontend
            'is_finalized' => $this->isSuccess(),
        ];
    }

    /**
     * Traduction simple des statuts pour l'UI
     */
    private function getStatusLabel(): string
    {
        return match($this->status) {
        'finished'   => 'Terminé',
            'confirming' => 'En cours de confirmation',
            'waiting'    => 'En attente de paiement',
            'expired'    => 'Expiré',
            'failed'     => 'Échoué',
            'partially_paid' => 'Paiement partiel',
            default      => ucfirst($this->status),
        };
    }
}
