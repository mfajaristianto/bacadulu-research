{{-- Tab cakupan: tautan nyata (?cakupan=), bukan tombol palsu. Setiap tab menampilkan jumlah aturan yang benar-benar ada. --}}
<nav class="bd-ab__tabs" aria-label="Cakupan aturan">
    @foreach ($tabs as $key => $label)
        @php $url = route('admin.answer-behavior.index', array_filter(array_merge(request()->except('page', 'cakupan'), ['cakupan' => $key ?: null]))); @endphp
        <a href="{{ $url }}" class="bd-ab__tab {{ $activeTab === $key ? 'is-on' : '' }}" data-dp-nav @if ($activeTab === $key) aria-current="page" @endif>{{ $label }} <span class="bd-ab__tab-n">{{ $tabCounts[$key] ?? 0 }}</span></a>
    @endforeach
</nav>

@if ($rows->isEmpty())
    <div class="bd-ix__empty">
        @if ($filtering)
            <h2>Tidak ada aturan yang cocok</h2>
            <p>Coba kata kunci lain, pilih tab lain, atau atur ulang filter.</p>
            <a href="{{ route('admin.answer-behavior.index') }}" class="bd-ix__btn" data-dp-reset>Atur ulang filter</a>
        @else
            <h2>Belum ada aturan jawaban</h2>
            <p>Tanpa aturan aktif, AI menjawab dengan perilaku bawaan sistem. Buat aturan pertama atau pasang preset awal sebagai draf, lalu tinjau dan aktifkan satu per satu.</p>
            <div class="bd-dp__head-actions">
                <a href="{{ route('admin.answer-behavior.index', ['baru' => 1]) }}" class="bd-ix__btn bd-ix__btn--primary" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'plus']) Buat aturan pertama</a>
            </div>
        @endif
    </div>
@else
    <div class="bd-ix__list-head bd-dp__list-head" aria-hidden="true"><span>Aturan</span><span>Cakupan</span><span>Versi</span><span>Status</span><span>Diperbarui</span></div>
    <ul class="bd-ix__list bd-dp__list" aria-label="Daftar aturan jawaban">
        @foreach ($rows as $r)
            @php $c = $r['config']; @endphp
            <li class="bd-ix__item">
                <a class="bd-ix__row bd-dp__row" href="{{ route('admin.answer-behavior.show', $r['uuid']) }}" data-dp-nav>
                    <div class="bd-ix__text">
                        <strong class="bd-ix__title">{{ $r['title'] }}</strong>
                        <span class="bd-ix__desc">{{ $r['description'] ?: ($groups[$r['group']]['hint'] ?? 'Tanpa deskripsi') }}</span>
                    </div>
                    <div class="bd-dp__cell" data-label="Cakupan">
                        <span>{{ $scopes[$r['scope']] ?? '—' }}@if ($r['tool_name']) · {{ $r['tool_name'] }}@elseif ($r['tool_code']) · {{ $r['tool_code'] }}@endif</span>
                        <span class="bd-ix__sub">{{ $groups[$r['group']]['label'] ?? 'Konfigurasi rusak' }}</span>
                    </div>
                    <div class="bd-dp__cell" data-label="Versi">
                        @if ($r['active'])
                            <strong>v{{ $r['active']->version }} aktif</strong>
                            <span class="bd-ix__sub">{{ $r['pending'] ? 'draf v'.$r['draft']->version.' menunggu' : $r['versions']->count().' versi' }}</span>
                        @elseif ($r['draft'])
                            <strong>Draf v{{ $r['draft']->version }}</strong>
                            <span class="bd-ix__sub">belum aktif</span>
                        @else
                            <strong>—</strong>
                            <span class="bd-ix__sub">{{ $r['versions']->count() }} versi diarsipkan</span>
                        @endif
                    </div>
                    <div class="bd-dp__cell" data-label="Status">
                        <span class="bd-ix__state bd-ix__state--{{ $r['state'] }}">{{ ['active' => 'Aktif', 'draft' => 'Draf', 'archived' => 'Diarsipkan'][$r['state']] }}</span>
                        @if ($r['tool_issue'])
                            <span class="bd-ix__warn">{{ $r['tool_issue'] }}</span>
                        @elseif ($r['pending'])
                            <span class="bd-ix__warn">Perlu ditinjau</span>
                        @elseif ($r['scope'] === 'document' && $r['state'] === 'active')
                            <span class="bd-ix__sub">belum terhubung ke alur dokumen</span>
                        @endif
                    </div>
                    <div class="bd-dp__cell bd-dp__cell--end" data-label="Diperbarui">
                        <span>{{ $wib($r['updated_at']) }}</span>
                        <span class="bd-ix__go">Kelola @include('admin.tools.partials.icon', ['name' => 'arrow'])</span>
                    </div>
                </a>
            </li>
        @endforeach
    </ul>
    <details class="bd-ix__group bd-ab__howto">
        <summary class="bd-ix__group-title">Cara aturan diterapkan</summary>
        <ol class="bd-ab__steps">
            <li><strong>Aturan tetap sistem</strong> selalu berlaku dan berada di atas semua aturan di sini. Aturan di sini hanya mengatur penyajian.</li>
            <li>Hanya versi <strong>aktif</strong> yang dipakai. Draf dan arsip tidak pernah ikut.</li>
            <li>Aturan berlaku bila cakupannya cocok: global (selalu), riset akademik, penulisan dokumen, atau alat yang sedang dipakai.</li>
            <li>Gaya, struktur, dan tindak lanjut: aturan yang <strong>paling khusus</strong> menang (alat, lalu mode, lalu global). Dua aturan aktif di slot yang sama tidak diizinkan.</li>
            <li>Kualitas &amp; kejujuran sumber: semua aturan yang cocok <strong>menumpuk</strong>; aturan khusus alat hanya menambah, tidak mencabut.</li>
            <li>Tanpa aturan aktif, AI memakai perilaku bawaan sistem. Perubahan berlaku untuk riset berikutnya; hasil lama tidak ditulis ulang.</li>
        </ol>
        <p class="bd-ix__hint">Pengaturan ini mengarahkan model lewat prompt dan menyaring hasilnya di server, tetapi perilaku model nyata tidak dapat dijamin. Pertanyaan lanjutan sudah tersimpan di data hasil riset, tetapi Research Workspace belum menampilkannya. Mode penulisan dokumen belum dipakai.</p>
    </details>

    @if ($rows->hasPages())
        <nav class="bd-ix__pager" aria-label="Halaman">
            @if ($rows->onFirstPage())
                <span class="bd-ix__btn bd-ix__btn--quiet" aria-disabled="true">Sebelumnya</span>
            @else
                <a class="bd-ix__btn" href="{{ $rows->previousPageUrl() }}" data-dp-nav>Sebelumnya</a>
            @endif
            <span class="bd-ix__muted">Halaman {{ $rows->currentPage() }} dari {{ $rows->lastPage() }}</span>
            @if ($rows->hasMorePages())
                <a class="bd-ix__btn" href="{{ $rows->nextPageUrl() }}" data-dp-nav>Berikutnya</a>
            @else
                <span class="bd-ix__btn bd-ix__btn--quiet" aria-disabled="true">Berikutnya</span>
            @endif
        </nav>
    @endif
@endif
