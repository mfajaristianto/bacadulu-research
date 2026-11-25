@if ($rows->isEmpty())
    <div class="bd-ix__empty">
        @if ($filtering)
            <h2>Tidak ada template yang cocok</h2>
            <p>Coba kata kunci lain atau atur ulang filter.</p>
            <a href="{{ route('admin.document-templates.index') }}" class="bd-ix__btn" data-dp-reset>Atur ulang filter</a>
        @else
            <h2>Belum ada template format</h2>
            <p>Template format menentukan ukuran halaman, margin, tipografi, heading, header dan footer, nomor halaman, serta daftar isi untuk ekspor Word dan PDF. Mulai dari preset lalu periksa semua pengaturannya.</p>
            <div class="bd-dp__head-actions">
                <a href="{{ route('admin.document-templates.index', ['baru' => 1]) }}" class="bd-ix__btn bd-ix__btn--primary" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'plus']) Buat template pertama</a>
            </div>
        @endif
    </div>
@else
    <div class="bd-ix__list-head bd-pf__list-head" aria-hidden="true"><span>Template</span><span>Halaman</span><span>Keluaran</span><span>Status</span><span>Diperbarui</span></div>
    <ul class="bd-ix__list bd-dp__list" aria-label="Daftar template format cetak">
        @foreach ($rows as $r)
            @php $t = $r['template']; $s = $r['state']; @endphp
            <li class="bd-ix__item bd-pf__item">
                <div class="bd-ix__row bd-pf__row">
                    <div class="bd-ix__text">
                        <strong class="bd-ix__title"><a href="{{ route('admin.document-templates.edit', $t) }}" data-dp-nav>{{ $t->name }}</a>@if ($t->is_default) <span class="bd-pf__default">default</span>@endif</strong>
                        <span class="bd-ix__desc">{{ $t->document_type }}@if ($r['category']) · {{ $r['category'] }}@endif</span>
                    </div>
                    <div class="bd-dp__cell" data-label="Halaman">
                        <strong>{{ $r['page'] }}</strong>
                        <span class="bd-ix__sub">{{ $r['legacy'] ? 'belum diatur di editor' : (optional($r['version'])['layout']['margins']['mirror'] ?? false ? 'margin cermin' : 'margin tetap') }}</span>
                    </div>
                    <div class="bd-dp__cell" data-label="Keluaran">
                        <span>DOCX · PDF</span>
                        <span class="bd-ix__sub">{{ $t->generations_count }} dokumen dibuat</span>
                    </div>
                    <div class="bd-dp__cell" data-label="Status">
                        <span class="bd-ix__state bd-ix__state--{{ ['active' => 'active', 'draft' => 'draft', 'legacy' => 'draft', 'archived' => 'archived'][$s['key']] }}">{{ $s['label'] }}</span>
                        <span class="bd-ix__sub">
                            @if ($r['active']) v{{ $r['active']['v'] }} aktif
                            @elseif ($r['version']) draf v{{ $r['version']['v'] }}
                            @else format bawaan generator @endif
                            @if ($r['active'] && collect($r['check']['items'])->contains('level', 'warn')) · perlu diperiksa @endif
                        </span>
                    </div>
                    <div class="bd-dp__cell bd-dp__cell--end" data-label="Diperbarui">
                        <span>{{ $wib($t->updated_at) }}</span>
                        <span class="bd-pf__actions">
                            <a class="bd-ix__btn bd-ix__btn--small" href="{{ route('admin.document-templates.edit', $t) }}" data-dp-nav aria-label="Kelola {{ $t->name }}">Kelola</a>
                            @if (! $r['legacy'] && $r['version'])
                                <a class="bd-ix__btn bd-ix__btn--small bd-ix__btn--quiet" href="{{ route('admin.document-templates.edit', ['documentTemplate' => $t->id, 'v' => $r['version']['v'], 'pratinjau' => 1]) }}" data-dp-nav aria-label="Pratinjau {{ $t->name }}">Pratinjau</a>
                            @endif
                        </span>
                    </div>
                </div>
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
