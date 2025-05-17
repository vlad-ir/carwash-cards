<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarwashBonusCardStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_id',
        'card_name',
        'card_type',
        'post',
        'start_time',
        'duration_seconds',
        'remaining_balance_seconds',
        'import_date',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'import_date' => 'date',
    ];

    public function card()
    {
        return $this->belongsTo(CarwashBonusCard::class, 'card_id');
    }
}
