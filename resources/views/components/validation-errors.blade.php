@if ($errors->any())
    <div {{ $attributes->class(['alert alert-danger']) }} role="alert">
        <p class="fw-semibold mb-2">Please correct the following {{ Str::plural('error', $errors->count()) }}:</p>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
