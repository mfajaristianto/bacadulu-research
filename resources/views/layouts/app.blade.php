<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'BacaDulu Research — evidence-grounded research from a trusted internal library.')">
    <meta name="theme-color" content="#1A1A2E">
    <title>@yield('title', 'BacaDulu Research')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ request()->routeIs('workspace*') ? 'research-chat-body' : '' }}">
    <a class="skip-link" href="#main-content">Skip to content</a>
    @include('components.navbar')
    <main id="main-content">@yield('content')</main>
    @unless(request()->routeIs('workspace*'))
        @include('components.footer')
    @endunless
    <div class="network-banner" data-network-banner role="status" aria-live="polite" hidden>
        You are offline. Unsaved form input stays on this page.
    </div>
</body>
</html>
