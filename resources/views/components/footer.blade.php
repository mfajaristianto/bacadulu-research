<footer class="site-footer" id="contact">
    <div class="footer-grid">
        <div class="footer-brand">
            <a href="{{ route('home') }}" class="footer-logo">BacaDulu <span>Research</span></a>
            <p>Research workspace berbasis sumber internal terkurasi, prompt terstruktur, dan alur kerja yang menjaga evidence tetap terlihat.</p>
        </div>
        <div><h4>Platform</h4><a href="{{ route('home') }}#workflow">Cara Kerja</a><a href="{{ route('home') }}#tools">Research Tools</a>@auth<a href="{{ route('workspace') }}">Workspace</a>@endauth</div>
        <div><h4>Explore</h4>@auth<a href="{{ route('library.index') }}">Internal Library</a><a href="{{ route('history.index') }}">History</a>@else<a href="{{ route('register') }}">Create account</a><a href="{{ route('login') }}">Login</a>@endauth</div>
    </div>
    <div class="footer-bottom"><span>© {{ date('Y') }} BacaDulu Research</span><span>Evidence first. Research with context.</span></div>
</footer>
