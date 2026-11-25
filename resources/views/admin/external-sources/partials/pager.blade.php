{{-- Paginasi sisi server ringkas (tanpa SVG bawaan framework). $paginator wajib; $fragment opsional. --}}
@if ($paginator->hasPages())
    <nav class="bd-ext__pager" aria-label="Navigasi halaman">
        @if ($paginator->onFirstPage())
            <span class="bd-ext__btn bd-ext__btn--small" aria-disabled="true">Sebelumnya</span>
        @else
            <a class="bd-ext__btn bd-ext__btn--small" href="{{ $paginator->previousPageUrl() }}#{{ $fragment ?? '' }}" rel="prev">Sebelumnya</a>
        @endif
        <p>Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }} · {{ number_format($paginator->total(), 0, ',', '.') }} data</p>
        @if ($paginator->hasMorePages())
            <a class="bd-ext__btn bd-ext__btn--small" href="{{ $paginator->nextPageUrl() }}#{{ $fragment ?? '' }}" rel="next">Berikutnya</a>
        @else
            <span class="bd-ext__btn bd-ext__btn--small" aria-disabled="true">Berikutnya</span>
        @endif
    </nav>
@endif
