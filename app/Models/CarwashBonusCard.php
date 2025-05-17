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
        'discount_percentage',
        'balance',
        'status',
        'car_license_plate',
        'rate_per_minute',
        'invoice_required',
        'client_id',
    ];

    protected $casts = [
        'invoice_required' => 'boolean',
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
