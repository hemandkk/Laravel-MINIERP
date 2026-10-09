<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') &middot; @endif{{ config('app.name', 'Mini ERP') }}</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <style>
        body { background-color: #f5f6f8; }
        .metric-value { font-size: 2rem; font-weight: 600; line-height: 1.2; }
        .table td, .table th { vertical-align: middle; }
        .line-items td { min-width: 7rem; }
        .line-items td.product-cell { min-width: 16rem; }
    </style>
    @stack('styles')
</head>
<body class="d-flex flex-column min-vh-100">
<a class="visually-hidden-focusable position-absolute top-0 start-0 p-2 bg-white" href="#main-content">Skip to main content</a>

@auth
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">Mini ERP</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav"
                    aria-controls="main-nav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="main-nav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    @foreach ([
                        ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Dashboard'],
                        ['route' => 'inventory.index', 'pattern' => 'inventory.*', 'label' => 'Inventory'],
                        ['route' => 'purchase-orders.index', 'pattern' => 'purchase-orders.*', 'label' => 'Purchase Orders'],
                    ] as $link)
                        @php($active = request()->routeIs($link['pattern']))
                        <li class="nav-item">
                            <a @class(['nav-link', 'active' => $active]) href="{{ route($link['route']) }}"
                               @if ($active) aria-current="page" @endif>{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <span class="navbar-text small">
                        {{ auth()->user()->name }}
                        <span class="badge text-bg-{{ auth()->user()->isAdmin() ? 'info' : 'light' }} ms-1">
                            {{ auth()->user()->isAdmin() ? 'Admin' : 'Viewer' }}
                        </span>
                    </span>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
@endauth

<main id="main-content" class="flex-grow-1 py-4">
    <div class="container">
        @if (session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        @yield('content')
    </div>
</main>

<footer class="py-3 border-top bg-white">
    <div class="container small text-muted">
        {{ config('app.name', 'Mini ERP') }} &middot; Inventory &amp; Purchase Order Management
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
<script>
    document.addEventListener('submit', function (event) {
        var message = event.target.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
</script>
@stack('scripts')
</body>
</html>
