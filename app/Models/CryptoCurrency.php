<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CryptoCurrency extends Model
{
    protected $fillable = [
        'name',
        'code',
        'network',
        'icon',
        'icon_res',
        'is_active'
    ];
}
