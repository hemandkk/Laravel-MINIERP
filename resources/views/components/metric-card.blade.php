@props(['title', 'value', 'variant' => 'primary', 'hint' => null, 'href' => null])

<div {{ $attributes->class(['card h-100 shadow-sm border-0 border-start border-4', "border-{$variant}"]) }}>
    <div class="card-body">
        <p class="text-uppercase text-muted small fw-semibold mb-1">{{ $title }}</p>
        <p class="metric-value text-{{ $variant }} mb-1">{{ $value }}</p>
        @if ($hint)
            <p class="small text-muted mb-0">
                @if ($href)
                    <a href="{{ $href }}" class="link-secondary">{{ $hint }}</a>
                @else
                    {{ $hint }}
                @endif
            </p>
        @endif
    </div>
</div>
