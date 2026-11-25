@if ($rows->isEmpty())
    <div class="bd-ix__empty">
        @if ($filtering)
            <h2>Tidak ada template yang cocok</h2>
            <p>Coba kata kunci lain atau atur ulang filter.</p>
            <a href="{{ route('admin.prompt-documents.index') }}" class="bd-ix__btn" data-dp-reset>Atur ulang filter</a>
        @else
            <h2>Belum ada template prompt</h2>
            <p>Template menentukan bagian apa saja yang ditulis AI untuk sebuah dokumen, urutannya, batas kata, dan instruksi tiap bagian. Buat template kosong, atau impor preset Book Chapter Akademik — Fajar lalu tinjau isinya sebelum disimpan sebagai draf.</p>
            <div class="bd-dp__head-actions">
                <a href="{{ route('admin.prompt-documents.index', ['baru' => 1]) }}" class="bd-ix__btn bd-ix__btn--primary" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'plus']) Buat template pertama</a>
                <a href="{{ route('admin.prompt-documents.index', ['word' => 1]) }}" class="bd-ix__btn" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'upload']) Impor dari Word</a>
            </div>
        @endif
    </div>
@else
    <div class="bd-ix__list-head bd-dp__list-head" aria-hidden="true"><span>Template</span><span>Jenis</span><span>Bagian</span><span>Status</span><span>Diperbarui</span></div>
    <ul class="bd-ix__list bd-dp__list" aria-label="Daftar template prompt dokumen">
        @foreach ($rows as $r)
            @php $t = $r['template']; $state = $r['state']; @endphp
            <li class="bd-ix__item">
                <a class="bd-ix__row bd-dp__row" href="{{ route('admin.prompt-documents.show', $t->id) }}" data-dp-nav>
                    <div class="bd-ix__text">
                        <strong class="bd-ix__title">{{ $t->name }}</strong>
                        <span class="bd-ix__desc">{{ $t->description ?: 'Tanpa deskripsi' }}</span>
                    </div>
                    <div class="bd-dp__cell" data-label="Jenis">
                        <span>{{ $t->document_type === 'other' && $t->type_label ? $t->type_label : $types[$t->document_type] }}</span>
                        <span class="bd-ix__sub">{{ $t->citation_style }} · {{ strtoupper($t->language) }}</span>
                    </div>
                    <div class="bd-dp__cell" data-label="Bagian">
                        <strong>{{ $r['with_prompt'] }}/{{ $r['enabled'] }}</strong>
                        <span class="bd-ix__sub">{{ $r['enabled'] === 0 ? 'belum ada bagian' : 'siap digunakan' }}</span>
                    </div>
                    <div class="bd-dp__cell" data-label="Status">
                        <span class="bd-ix__state bd-ix__state--{{ in_array($state['key'], ['ready', 'unsupported'], true) ? 'active' : ($state['key'] === 'archived' ? 'archived' : 'draft') }}">{{ $state['label'] }}</span>
                        @if ($state['key'] === 'incomplete')
                            <span class="bd-ix__warn">{{ count($r['readiness']['issues']) }} hal perlu dilengkapi</span>
                        @elseif ($state['key'] === 'draft' && $r['readiness']['issues'])
                            <span class="bd-ix__sub">{{ count($r['readiness']['issues']) }} syarat sebelum bisa aktif</span>
                        @endif
                    </div>
                    <div class="bd-dp__cell bd-dp__cell--end" data-label="Diperbarui">
                        <span>{{ $wib($t->updated_at) }}</span>
                        <span class="bd-ix__go">Kelola @include('admin.tools.partials.icon', ['name' => 'arrow'])</span>
                    </div>
                </a>
            </li>
        @endforeach
    </ul>
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
