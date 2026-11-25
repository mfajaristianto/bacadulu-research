@php
    $wib = fn ($date) => $date?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i');
@endphp
@if ($groups->isEmpty())
    <div class="bd-ix__empty">
        @if ($filtering)
            <h2>Tidak ada instruksi yang cocok</h2>
            <p>Coba kata kunci lain atau atur ulang filter.</p>
            <a href="{{ route('admin.prompts.index') }}" class="bd-ix__btn" data-ix-reset>Atur ulang filter</a>
        @else
            <h2>Belum ada instruksi</h2>
            <p>Instruksi menentukan bagaimana AI bekerja untuk sebuah alat riset. Buat yang pertama, lalu jadikan aktif agar alat bisa dipakai.</p>
            <a href="{{ route('admin.prompts.index', ['baru' => 1]) }}" class="bd-ix__btn bd-ix__btn--primary" data-ix-nav>@include('admin.tools.partials.icon', ['name' => 'plus']) Buat instruksi pertama</a>
        @endif
    </div>
@else
    <div class="bd-ix__list-head" aria-hidden="true"><span>Instruksi</span><span>Alat terhubung</span><span>Versi aktif</span><span>Status</span><span>Diperbarui</span></div>
    <ul class="bd-ix__list" aria-label="Daftar instruksi">
        @foreach ($groups as $g)
            @php
                $subject = $g->active ?? $g->latest;
                $draft = $g->versions->firstWhere('status', 'draft');
            @endphp
            <li class="bd-ix__item">
                <a class="bd-ix__row" href="{{ $g->url }}" data-ix-nav>
                    <div class="bd-ix__text">
                        <strong class="bd-ix__title">{{ $g->name }}</strong>
                        <span class="bd-ix__desc">{{ $subject?->indonesian_name ?: ($subject?->short_description ?: 'Tanpa deskripsi') }}</span>
                    </div>
                    <div class="bd-ix__tool">
                        @if ($g->tool)
                            <span>{{ $g->tool->name }}</span>
                            <code>{{ $g->tool->code }}</code>
                        @else
                            <span class="bd-ix__warn">Belum terhubung ke alat</span>
                        @endif
                    </div>
                    <div class="bd-ix__ver">
                        @if ($g->active)
                            <strong>v{{ $g->active->version }}</strong>
                        @else
                            <span class="bd-ix__sub">Belum ada</span>
                        @endif
                        <span class="bd-ix__sub">{{ $g->versions->count() }} {{ $g->versions->count() === 1 ? 'versi' : 'versi' }}@if ($draft) · draf v{{ $draft->version }}@endif</span>
                    </div>
                    <div class="bd-ix__status">
                        @if ($g->ready)
                            <span class="bd-ix__state bd-ix__state--active">Siap dipakai</span>
                        @elseif ($g->active && $g->tool?->status === 'archived')
                            <span class="bd-ix__state bd-ix__state--archived">Aktif</span>
                            <span class="bd-ix__sub">Alat diarsipkan, tidak tersedia untuk pengguna</span>
                        @elseif ($g->active)
                            <span class="bd-ix__state bd-ix__state--active">Aktif</span>
                            <span class="bd-ix__warn">{{ $g->tool ? 'Penunjuk alat belum sinkron' : 'Belum terhubung ke alat' }}</span>
                        @elseif ($draft)
                            <span class="bd-ix__state bd-ix__state--draft">Draf</span>
                            <span class="bd-ix__warn">Belum ada versi aktif</span>
                        @else
                            <span class="bd-ix__state bd-ix__state--archived">Diarsipkan</span>
                        @endif
                    </div>
                    <time class="bd-ix__when" datetime="{{ $g->updated?->toIso8601String() }}">{{ $wib($g->updated) }}</time>
                    <span class="bd-ix__go">Kelola<span class="bd-ix__sr"> {{ $g->name }}</span>@include('admin.tools.partials.icon', ['name' => 'arrow'])</span>
                </a>
            </li>
        @endforeach
    </ul>

    @if ($groups->hasPages())
        <nav class="bd-ix__pager" aria-label="Halaman instruksi">
            <p class="bd-ix__muted">{{ $groups->firstItem() }}–{{ $groups->lastItem() }} dari {{ $groups->total() }} instruksi</p>
            <div>
                @if ($groups->onFirstPage())
                    <span class="bd-ix__btn bd-ix__btn--quiet bd-ix__btn--small" aria-disabled="true">Sebelumnya</span>
                @else
                    <a class="bd-ix__btn bd-ix__btn--small" href="{{ $groups->previousPageUrl() }}" data-ix-nav rel="prev">Sebelumnya</a>
                @endif
                @if ($groups->hasMorePages())
                    <a class="bd-ix__btn bd-ix__btn--small" href="{{ $groups->nextPageUrl() }}" data-ix-nav rel="next">Berikutnya</a>
                @else
                    <span class="bd-ix__btn bd-ix__btn--quiet bd-ix__btn--small" aria-disabled="true">Berikutnya</span>
                @endif
            </div>
        </nav>
    @endif
@endif
