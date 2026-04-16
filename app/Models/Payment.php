<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés massivement.
     */
    protected $fillable = [
        'user_id',
        'reference',
        'provider_id',
        'fiat_amount',
        'fiat_currency',
        'crypto_currency',
        'crypto_amount',
        'pay_address',
        'recipient_address',
        'status',
        'provider_response',
        'processed_at',
    ];

    /**
     * Le typage des colonnes (Casting).
     */
    protected $casts = [
        'fiat_amount' => 'decimal:2',
        'crypto_amount' => 'decimal:8',
        'provider_response' => 'array', // Transforme le JSON en tableau PHP automatiquement
        'processed_at' => 'datetime',
    ];
    protected $hidden = ['provider_response'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($payment) {
            if (empty($payment->reference)) {
                $payment->reference = 'PAY-' . strtoupper(Str::random(10));
            }
        });
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES (Pour faciliter les requêtes)
    |--------------------------------------------------------------------------
    */

    public function scopeFinished($query)
    {
        return $query->where('status', 'finished');
    }

    public function scopeWaiting($query)
    {
        return $query->whereIn('status', ['waiting', 'confirming']);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSEURS (Utiles pour l'UI Android)
    |--------------------------------------------------------------------------
    */

    /**
     * Vérifie si le paiement est finalisé.
     */
    public function isSuccess(): bool
    {
        return $this->status === 'finished';
    }

    /**
     * Retourne une couleur selon le statut (pratique pour ton API).
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
        'finished' => '#2e7d32', // Vert
            'failed', 'expired' => '#d32f2f', // Rouge
            'confirming' => '#f9a825', // Orange
            default => '#1976d2', // Bleu (waiting)
        };
    }
}
