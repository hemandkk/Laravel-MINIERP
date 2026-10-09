<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(PurchaseOrderStatus::class)],
            'supplier_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function status(): ?PurchaseOrderStatus
    {
        return PurchaseOrderStatus::tryFrom((string) $this->validated('status'));
    }

    public function supplierId(): ?int
    {
        $supplierId = $this->validated('supplier_id');

        return $supplierId === null ? null : (int) $supplierId;
    }
}
