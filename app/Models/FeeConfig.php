<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeConfig extends Model
{
    protected $fillable = [
        'crypto',
        'network',
        'percent_fee',
        'fixed_fee',
        'is_active'
    ];

    protected $casts = [
        'percent_fee' => 'decimal:2',
        'fixed_fee' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
