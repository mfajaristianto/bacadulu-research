{{-- Isi panel satu koleksi (dimuat saat dibuka). $collection, $documents, $histories (SourceVersions), $total. --}}
@php
    $size = fn (int $bytes): string => $bytes >= 1048576
        ? number_format($bytes / 1048576, 1, ',', '.').' MB'
        : number_format(max($bytes, 1) / 1024, 0, ',', '.').' KB';
@endphp
<div class="bd-col__members-head">
    <p class="bd-col__muted"><strong>{{ number_format($total, 0, ',', '.') }}</strong> sumber di koleksi ini @if ($total > $documents->count()) · menampilkan {{ $documents->count() }} pertama @endif</p>
    <button type="button" class="bd-col__btn bd-col__btn--small" data-col-add data-id="{{ $collection->id }}" data-name="{{ $collection->name }}">@include('admin.collections.partials.icon', ['name' => 'plus']) Tambah sumber</button>
</div>

@if ($documents->isEmpty())
    <p class="bd-col__muted bd-col__members-empty">Koleksi ini belum berisi sumber. Pilih dari Pustaka dengan Tambah sumber.</p>
@else
    <ul class="bd-col__members" data-count="{{ $total }}">
        @foreach ($documents as $document)
            @php
                $history = $histories[$document->id] ?? [];
                $current = collect($history)->firstWhere('is_active', true) ?? ($history[0] ?? null);
                $meta = array_filter([
                    $current['name'] ?? null,
                    $document->publication_year,
                    $document->doi ? 'DOI '.$document->doi : null,
                    $document->category?->name,
                ], fn ($part) => filled($part));
            @endphp
            <li class="bd-col__member">
                @if ($current)
                    <span class="bd-col__pdf" aria-hidden="true">PDF</span>
                @else
                    <span class="bd-col__pdf-gap" aria-hidden="true"></span>
                @endif
                <div class="bd-col__member-main">
                    <strong>{{ $document->title }}</strong>
                    <span>{{ implode(' · ', $meta) }}@if ($current && count($history) > 1) · {{ $current['label'] }} dari {{ count($history) }} versi @endif</span>
                </div>
                <div class="bd-col__member-actions">
                    @if ($current && empty($current['missing']))
                        <button type="button" class="bd-col__btn bd-col__btn--quiet bd-col__btn--small" data-lib-view data-url="{{ route('admin.library.files.view', $current['id']) }}" data-download="{{ route('admin.library.files.download', $current['id']) }}" data-title="{{ $document->title }}" data-version="{{ $current['label'] }} · {{ $current['state_label'] }}" aria-label="Lihat PDF {{ $document->title }}">Lihat</button>
                    @endif
                    <a class="bd-col__btn bd-col__btn--quiet bd-col__btn--small" href="{{ route('admin.library.edit', $document) }}">Pustaka</a>
                    <button type="button" class="bd-col__btn bd-col__btn--quiet bd-col__btn--small bd-col__btn--danger" data-col-remove data-url="{{ route('admin.collections.detach', [$collection, $document]) }}" data-title="{{ $document->title }}" data-collection="{{ $collection->name }}" aria-label="Lepas {{ $document->title }} dari koleksi">Lepas</button>
                </div>
            </li>
        @endforeach
    </ul>
@endif
