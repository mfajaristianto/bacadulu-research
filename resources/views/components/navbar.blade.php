<header class="site-header">
    <nav class="navbar" aria-label="Primary navigation">
        <a href="{{ route('home') }}" class="brand" aria-label="BacaDulu Research home"><span>BacaDulu</span><em>Research</em></a>
        <div class="nav-links">
            @auth
                @if(auth()->user()->profileCompletion() >= config('research.profile_gate', 80))
                    <a href="{{ route('workspace') }}" class="{{ request()->routeIs('workspace*') ? 'is-active' : '' }}">Workspace</a>
                    <a href="{{ route('library.index') }}" class="{{ request()->routeIs('library.*') ? 'is-active' : '' }}">Library</a>
                    <a href="{{ route('history.index') }}" class="{{ request()->routeIs('history.*') ? 'is-active' : '' }}">History</a>
                @endif
                <a href="{{ route('profile.edit') }}" class="avatar-link {{ request()->routeIs('profile.*') ? 'is-active' : '' }}" aria-label="Open profile">
                    <span class="avatar avatar-fallback">{{ auth()->user()->initials }}</span>
                    @if(auth()->user()->avatar_url)
                        <img src="{{ auth()->user()->avatar_url }}" alt="" class="avatar avatar-image" data-avatar-image decoding="async" referrerpolicy="no-referrer">
                    @endif
                </a>
                <form method="POST" action="{{ route('logout') }}" class="inline-form">@csrf<button class="nav-logout" type="submit">Logout</button></form>
            @else
                <a href="{{ route('home') }}#workflow">Cara Kerja</a>
                <a href="{{ route('home') }}#tools">Research Tools</a>
                <a href="{{ route('login') }}" class="login-btn">Login</a>
            @endauth
        </div>
        <button class="mobile-toggle" type="button" aria-label="Open navigation" aria-expanded="false" data-mobile-menu><span></span><span></span><span></span></button>
    </nav>
    <div class="mobile-menu" data-mobile-panel>
        @auth
            @if(auth()->user()->profileCompletion() >= config('research.profile_gate', 80))
                <a href="{{ route('workspace') }}">Workspace</a>
                <a href="{{ route('library.index') }}">Internal Library</a>
                <a href="{{ route('history.index') }}">History</a>
            @endif
            <a href="{{ route('profile.edit') }}">My Profile</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Logout</button></form>
        @else
            <a href="{{ route('home') }}#workflow">Cara Kerja</a>
            <a href="{{ route('home') }}#tools">Research Tools</a>
            <a href="{{ route('login') }}" class="mobile-login">Login</a>
        @endauth
    </div>
</header>
