@props(['paginator'])

@if ($paginator->hasPages())
    <nav {{ $attributes }} aria-label="Pagination">{{ $paginator->onEachSide(1)->links() }}</nav>
@elseif ($paginator->total() > 0)
    <p {{ $attributes->class(['small text-muted mb-0']) }}>
        Showing {{ $paginator->total() }} {{ Str::plural('result', $paginator->total()) }}
    </p>
@endif
