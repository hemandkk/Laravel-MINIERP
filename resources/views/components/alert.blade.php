@props(['type' => 'info', 'dismissible' => true])

<div {{ $attributes->class(['alert', "alert-{$type}", 'alert-dismissible fade show' => $dismissible]) }}
     role="{{ in_array($type, ['danger', 'warning'], true) ? 'alert' : 'status' }}">
    {{ $slot }}
    @if ($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif
</div>
