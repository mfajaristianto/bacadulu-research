<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="BacaDulu Research administration console.">
    <meta name="theme-color" content="#1A1A2E">
    <title>@yield('title', 'Admin Console — BacaDulu Research')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-shell">
    <a class="skip-link" href="#admin-main">Skip to content</a>
    <header class="admin-header">
        <div class="admin-nav-wrap">
            <a href="{{ route('admin.dashboard') }}" class="admin-brand"><span>BacaDulu</span> Research <small>ADMIN</small></a>
            <button class="admin-mobile-toggle" type="button" aria-label="Open admin navigation" aria-expanded="false" data-admin-menu><span></span><span></span><span></span></button>
            <nav class="admin-nav" data-admin-nav aria-label="Admin navigation">
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">Overview</a>
                <a href="{{ route('admin.library.index') }}" class="{{ request()->routeIs('admin.library.*') ? 'is-active' : '' }}">Internal Library</a>
                <a href="{{ route('admin.prompts.index') }}" class="{{ request()->routeIs('admin.prompts.*') ? 'is-active' : '' }}">Prompt Library</a>
                <a href="{{ route('home') }}" target="_blank" rel="noopener">View Site ↗</a>
                <span class="admin-divider"></span>
                <span class="admin-user">{{ session('research_admin_email', config('research.admin_email')) }}</span>
                <form method="POST" action="{{ route('admin.logout') }}" class="inline-form">@csrf<button class="admin-logout" type="submit">Logout</button></form>
            </nav>
        </div>
    </header>
    <main class="admin-main" id="admin-main">
        @if(session('success'))<div class="admin-toast" data-admin-toast role="status">{{ session('success') }}</div>@endif
        @yield('content')
    </main>
    <footer class="admin-footer"><span>BacaDulu Research Admin</span><span>Internal knowledge &amp; AI configuration</span></footer>
</body>
</html>
