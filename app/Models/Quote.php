<?php


namespace App\Models;


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Quote extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'crypto',
        'network',
        'amount_xaf',
        'rate',
        'amount_crypto',
        'fee',
        'total_crypto',
        'expires_at'
    ];

    protected $casts = [
        'amount_xaf' => 'decimal:2',
        'rate' => 'decimal:6',
        'amount_crypto' => 'decimal:8',
        'fee' => 'decimal:8',
        'total_crypto' => 'decimal:8',
        'expires_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($quote) {
            if (!$quote->reference) {
                $quote->reference = Str::uuid();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIC
    |--------------------------------------------------------------------------
    */

    public function isExpired()
    {
        return now()->greaterThan($this->expires_at);
    }
}
