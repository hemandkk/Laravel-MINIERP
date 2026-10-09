@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5">
            <div class="text-center my-4">
                <h1 class="h3 fw-semibold">Mini ERP</h1>
                <p class="text-muted">Inventory &amp; Purchase Order Management</p>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Sign in to your account</h2>

                    <form method="POST" action="{{ route('login.store') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                   @class(['form-control', 'is-invalid' => $errors->has('email')])
                                   autocomplete="username" required autofocus
                                   @error('email') aria-describedby="email-error" @enderror>
                            @error('email')
                                <div id="email-error" class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password"
                                   @class(['form-control', 'is-invalid' => $errors->has('password')])
                                   autocomplete="current-password" required
                                   @error('password') aria-describedby="password-error" @enderror>
                            @error('password')
                                <div id="password-error" class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember"
                                   @checked(old('remember'))>
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Sign in</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
