<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarwashClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'unp' => $this->unp,
            'full_name' => $this->full_name,
            'short_name' => $this->short_name,
            'contract' => $this->contract,
            'bank_account_number' => $this->bank_account_number,
            'bank_bic' => $this->bank_bic,
            'postal_address' => $this->postal_address,
            'bank_postal_address' => $this->bank_postal_address,
            'email' => $this->email,
        ];
    }
}
