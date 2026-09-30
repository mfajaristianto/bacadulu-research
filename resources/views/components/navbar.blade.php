@php
    $user = auth()->user();
    $researchAccess = auth()->check()
        && $user->profileCompletion() >= config('research.profile_gate', 80);
@endphp

<header class="site-header">
    <nav class="navbar" aria-label="Navigasi utama">
        <a href="{{ route('home') }}" class="brand" aria-label="BacaDulu Research — beranda">
            <span>BacaDulu</span>
            <em>Research</em>
        </a>

        <div class="nav-links">
            @auth
                @if($researchAccess)
                    <a
                        href="{{ route('workspace') }}"
                        class="{{ request()->routeIs('workspace*') ? 'is-active' : '' }}"
                        {!! request()->routeIs('workspace*') ? 'aria-current="page"' : '' !!}
                    >
                        Research
                    </a>
                @endif

                <a
                    href="{{ route('profile.edit') }}"
                    class="avatar-link {{ request()->routeIs('profile.*') ? 'is-active' : '' }}"
                    aria-label="Buka profil"
                    {!! request()->routeIs('profile.*') ? 'aria-current="page"' : '' !!}
                >
                    @if($user->avatar_url)
                        <img
                            src="{{ $user->avatar_url }}"
                            alt=""
                            class="avatar avatar-image"
                            data-avatar-image
                            decoding="async"
                            referrerpolicy="no-referrer"
                        >
                    @else
                        <span class="avatar avatar-fallback">{{ $user->initials }}</span>
                    @endif
                </a>

                <form method="POST" action="{{ route('logout') }}" class="inline-form">
                    @csrf
                    <button class="nav-logout" type="submit">Keluar</button>
                </form>
            @else
                <a href="{{ route('home') }}#workflow">Cara Kerja</a>
                <a href="{{ route('home') }}#tools">Research Tools</a>
                <a href="{{ route('login') }}" class="login-btn">Masuk</a>
            @endauth
        </div>

        <button
            class="mobile-toggle"
            type="button"
            aria-label="Buka navigasi"
            aria-expanded="false"
            aria-controls="mobile-navigation"
            data-mobile-menu
        >
            <span></span><span></span><span></span>
        </button>
    </nav>

    <div class="mobile-menu" id="mobile-navigation" data-mobile-panel aria-hidden="true">
        @auth
            @if($researchAccess)
                <a href="{{ route('workspace') }}" {!! request()->routeIs('workspace*') ? 'aria-current="page"' : '' !!}>
                    Research
                </a>
            @endif

            <a href="{{ route('profile.edit') }}" {!! request()->routeIs('profile.*') ? 'aria-current="page"' : '' !!}>
                Profil Saya
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Keluar</button>
            </form>
        @else
            <a href="{{ route('home') }}#workflow">Cara Kerja</a>
            <a href="{{ route('home') }}#tools">Research Tools</a>
            <a href="{{ route('login') }}" class="mobile-login">Masuk</a>
        @endauth
    </div>
</header>
