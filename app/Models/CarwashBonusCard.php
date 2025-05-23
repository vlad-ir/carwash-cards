<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarwashBonusCard extends Model
{
    use HasFactory;

    protected $table = 'carwash_bonus_cards';

    protected $fillable = [
        'name',
        'card_number',
        'status',
        'rate_per_minute',
        'client_id',
    ];

    public function client()
    {
        return $this->belongsTo(CarwashClient::class, 'client_id');
    }
    public function stats()
    {
        return $this->hasMany(CarwashBonusCardStat::class, 'card_id');
    }
}
