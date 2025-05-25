<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarwashInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'amount',
        'total_cards_count',
        'active_cards_count',
        'blocked_cards_count',
        'period_start',
        'period_end',
        'file_path',
        'sent_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'sent_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(CarwashClient::class);
    }
}
