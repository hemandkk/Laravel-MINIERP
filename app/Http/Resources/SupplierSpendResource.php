<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Supplier;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class SupplierSpendResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'supplier_id' => $this->id,
            'supplier_name' => $this->name,
            'received_orders_count' => (int) $this->received_orders_count,
            'total_spend' => Money::normalize($this->total_spend),
        ];
    }
}
