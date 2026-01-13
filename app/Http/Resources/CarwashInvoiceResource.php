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
        $vatRatePercent = (float) config('billing.vat_rate', 20);
        $vatRate = $vatRatePercent / 100;

        $amount = (float) $this->amount;
        $vatAmount = round($amount * $vatRate, 2);
        $totalAmount = $amount + $vatAmount;

        return [
            'invoice_date' => $this->created_at->toDateString(),
            'amount' => $amount,
            'vat_rate' => $vatRatePercent,
            'vat_amount' => $vatAmount,
            'total_amount' => $totalAmount,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'client' => new CarwashClientResource($this->whenLoaded('client')),
        ];
    }
}
