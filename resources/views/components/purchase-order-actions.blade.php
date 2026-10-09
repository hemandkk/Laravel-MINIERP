@props(['purchaseOrder'])

@php
    // Presentation mapping only; which transitions exist is decided by PurchaseOrderStatus.
    $actions = [
        'APPROVED' => ['route' => 'purchase-orders.approve', 'label' => 'Approve', 'class' => 'btn-primary',
            'confirm' => 'Approve this purchase order?'],
        'RECEIVED' => ['route' => 'purchase-orders.receive', 'label' => 'Mark as Received', 'class' => 'btn-success',
            'confirm' => 'Mark this purchase order as received? Stock will be added to inventory.'],
        'CANCELLED' => ['route' => 'purchase-orders.cancel', 'label' => 'Cancel Order', 'class' => 'btn-outline-danger',
            'confirm' => 'Cancel this purchase order? This cannot be undone.'],
    ];
    $transitions = $purchaseOrder->status->allowedTransitions();
@endphp

@if ($transitions === [])
    <p {{ $attributes->class(['text-muted small mb-0']) }}>
        This purchase order is {{ $purchaseOrder->status->value }} and can no longer be modified.
    </p>
@else
    <div {{ $attributes->class(['d-flex flex-wrap gap-2']) }}>
        @foreach ($transitions as $target)
            @php($action = $actions[$target->value])
            <form method="POST" action="{{ route($action['route'], $purchaseOrder) }}" data-confirm="{{ $action['confirm'] }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn {{ $action['class'] }}">{{ $action['label'] }}</button>
            </form>
        @endforeach
    </div>
@endif
