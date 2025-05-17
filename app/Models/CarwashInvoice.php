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
        'period_start',
        'period_end',
        'pdf_path',
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
