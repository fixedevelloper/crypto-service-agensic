<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
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

            // Détails financiers (XAF)
            'fiat' => [
                'amount'   => (float) $this->amount_xaf,
                'currency' => 'XAF',
                'rate'     => (float) $this->rate, // Taux appliqué
                'display'  => number_format($this->amount_xaf, 0, ',', ' ') . ' XAF',
            ],

            // Détails Blockchain (Crypto)
            'crypto' => [
                'symbol'        => strtoupper($this->crypto),
                'network'       => strtoupper($this->network),
                'amount_raw'    => (float) $this->amount_crypto,
                'fee'           => (float) $this->fee,
                'total_sent'    => (float) $this->total_crypto,
                'recipient'     => $this->recipient_address,
                'tx_hash'       => $this->tx_hash,
                'explorer_url'  => $this->getExplorerUrl(),
            ],

            // Statut & Badge
            'status' => [
                'value' => $this->status,
                'label' => $this->getStatusLabel(),
                'color' => $this->getStatusColor(),
            ],

            // Relations (Hydratées manuellement ou via microservice)
            'user'        => $this->user_data ?? ['id' => $this->user_id],
            'beneficiary' => new BeneficiaryResource($this->whenLoaded('beneficiary')),
            'quote'       => $this->whenLoaded('quote'),

            // Dates
            'processed_at' => $this->processed_at ? $this->processed_at->format('d/m/Y H:i') : null,
            'created_at'   => $this->created_at->format('d/m/Y H:i'),

            // Metadata pour l'UI Next.js
            'is_pending' => $this->status === 'pending',
            'is_success' => $this->status === 'success',
        ];
    }

    /**
     * Génère l'URL de l'explorer selon le réseau
     */
    private function getExplorerUrl(): ?string
    {
        if (!$this->tx_hash) return null;

        return match(strtolower($this->network)) {
        'trc20', 'tron' => "https://tronscan.org/#/transaction/{$this->tx_hash}",
            'erc20', 'eth'  => "https://etherscan.io/tx/{$this->tx_hash}",
            'bsc', 'bep20'  => "https://bscscan.com/tx/{$this->tx_hash}",
            default         => null,
        };
    }

    private function getStatusLabel(): string
    {
        return match($this->status) {
        'pending' => 'En attente',
            'success' => 'Terminé',
            'failed'  => 'Échoué',
            default   => ucfirst($this->status),
        };
    }

    private function getStatusColor(): string
    {
        return match($this->status) {
        'success' => '#10b981', // Emerald-500
            'pending' => '#f59e0b', // Amber-500
            'failed'  => '#ef4444', // Red-500
            default   => '#64748b', // Slate-500
        };
    }
}
