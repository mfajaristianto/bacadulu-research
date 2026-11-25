{{-- Daftar sumber Pustaka yang belum ada di koleksi (pemilih). $collection, $candidates (simplePaginate), $searching. --}}
@if ($candidates->isEmpty())
    <p class="bd-col__muted bd-col__picker-empty">{{ $searching ? 'Tidak ada sumber yang cocok, atau semuanya sudah ada di koleksi ini.' : 'Semua sumber Pustaka sudah ada di koleksi ini, atau Pustaka masih kosong.' }}</p>
@else
    <ul class="bd-col__cands">
        @foreach ($candidates as $document)
            <li>
                <label class="bd-col__cand">
                    <span class="bd-col__check"><input type="checkbox" value="{{ $document->id }}" data-col-pick data-title="{{ $document->title }}"><i></i></span>
                    <span class="bd-col__cand-text">
                        <strong>{{ $document->title }}</strong>
                        <small>{{ implode(' · ', array_filter([$document->category?->name, $document->publication_year, $document->doi ? 'DOI '.$document->doi : null], fn ($p) => filled($p))) ?: 'Tanpa metadata' }}</small>
                    </span>
                </label>
            </li>
        @endforeach
    </ul>

    @if ($candidates->hasPages())
        <div class="bd-col__picker-pager">
            <button type="button" class="bd-col__btn bd-col__btn--quiet bd-col__btn--small" data-col-page="{{ $candidates->currentPage() - 1 }}" @disabled($candidates->onFirstPage())>Sebelumnya</button>
            <span class="bd-col__muted">Halaman {{ $candidates->currentPage() }}</span>
            <button type="button" class="bd-col__btn bd-col__btn--quiet bd-col__btn--small" data-col-page="{{ $candidates->currentPage() + 1 }}" @disabled(! $candidates->hasMorePages())>Berikutnya</button>
        </div>
    @endif
@endif
