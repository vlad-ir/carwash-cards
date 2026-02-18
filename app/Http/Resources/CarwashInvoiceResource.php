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

        $totalAmount = (float) $this->amount;
        $amount = $calculateVat ? round($totalAmount / (1+$vatRate), 2) : 0.0;
        $vatAmount = $calculateVat ? round($totalAmount - $amount, 2) : 0.0;

        return [
            'invoice_date' => $this->created_at->toDateString(),
            'invoice_number' => 'АМ-'.$this->id,
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
