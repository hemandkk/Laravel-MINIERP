<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class UpdatePurchaseOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchaseOrder');

        return $purchaseOrder instanceof PurchaseOrder
            && (bool) $this->user()?->can('changeStatus', $purchaseOrder);
    }


    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::enum(PurchaseOrderStatus::class)->only(PurchaseOrderStatus::transitionTargets()),
            ],
        ];
    }

    public function targetStatus(): PurchaseOrderStatus
    {
        return PurchaseOrderStatus::from((string) $this->validated('status'));
    }
}
