<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarwashClient extends Model
{
    use HasFactory;

    protected $table = 'carwash_clients';

    protected $fillable = [
        'short_name',
        'full_name',
        'email',
        'unp',
        'bank_account_number',
        'bank_bic',
        'status',
        'invoice_email_required',
        'invoice_email_day',
        'postal_address',
        'bank_postal_address',
        'contract',
    ];

    protected $casts = [
        'invoice_email_day' => 'integer',
        'invoice_email_required' => 'boolean',
    ];

    public function bonusCards()
    {
        return $this->hasMany(CarwashBonusCard::class);
    }

    public function invoices()
    {
        return $this->hasMany(CarwashInvoice::class);
    }
}
