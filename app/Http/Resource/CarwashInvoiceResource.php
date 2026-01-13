<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarwashInvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vatRate = (float) config('invoice.vat_percentage', 0.20);
        $calculateVat = config('invoice.calculate_vat', true);

        $amount = (float) $this->amount;
        $vatAmount = $calculateVat ? round($amount * $vatRate, 2) : 0.0;
        $totalAmount = $amount + $vatAmount;

        return [
            'invoice_date' => $this->created_at->toDateString(),
            'amount' => $amount,
            'vat_rate' => $vatRate * 100,
            'vat_amount' => $vatAmount,
            'total_amount' => $totalAmount,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'client' => new CarwashClientResource($this->whenLoaded('client')),
        ];
    }
}
