<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'beneficiary_id',
        'quote_id',
        'crypto',
        'network',
        'amount_xaf',
        'rate',
        'amount_crypto',
        'fee',
        'total_crypto',
        'recipient_address',
        'tx_hash',
        'status',
        'provider_response',
        'processed_at'
    ];

    protected $casts = [
        'amount_xaf' => 'decimal:2',
        'rate' => 'decimal:6',
        'amount_crypto' => 'decimal:8',
        'fee' => 'decimal:8',
        'total_crypto' => 'decimal:8',
        'provider_response' => 'array',
        'processed_at' => 'datetime',
    ];

    // 🔥 UUID auto
    protected static function booted()
    {
        static::creating(function ($transaction) {
            if (!$transaction->reference) {
                $transaction->reference = Str::uuid();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function logs()
    {
        return $this->hasMany(TransferLog::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }
}
