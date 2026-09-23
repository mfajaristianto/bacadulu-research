@extends('layouts.admin-auth')
@section('title', 'Admin Login — BacaDulu Research')
@section('content')
<div class="admin-auth-grid">
    <section class="admin-auth-intro" data-reveal>
        <a href="{{ route('home') }}" class="admin-brand admin-brand-large"><span>BacaDulu</span> Research</a>
        <div class="admin-auth-copy"><p class="eyebrow">RESTRICTED CONSOLE</p><h1>Build the knowledge behind the research assistant.</h1><p>Manage internal sources, research prompts, and the controls that determine what users can retrieve.</p></div>
        <div class="admin-auth-note"><span></span> Private administration area</div>
    </section>
    <section class="admin-login-card" data-reveal>
        <div class="admin-login-head"><span class="admin-kicker">ADMIN ACCESS</span><h2>Welcome back.</h2><p>Sign in with the admin credentials configured for this installation.</p></div>
        @if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('admin.login.store') }}" class="admin-login-form">
            @csrf
            <label>Email address<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
            <label>Password<span class="password-field"><input type="password" name="password" autocomplete="current-password" required data-password-input><button type="button" class="password-toggle" aria-label="Show password" data-password-toggle>Show</button></span></label>
            <button class="btn btn-primary btn-full" type="submit">Enter Console <span>↗</span></button>
        </form>
        <a href="{{ route('home') }}" class="admin-back-link">← Back to BacaDulu Research</a>
    </section>
</div>
@endsection
