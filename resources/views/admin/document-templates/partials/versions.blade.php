@php
    $wibShort = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i') : null;
    $vlabel = fn ($v) => ['active' => 'Sedang aktif', 'draft' => 'Draf', 'archived' => 'Diarsipkan'][$v['status']];
    $vurl = fn ($v) => route('admin.document-templates.edit', ['documentTemplate' => $template->id, 'v' => $v['v']]);
    $labels = [
        'document_template.created' => 'Template dibuat', 'document_template.duplicated' => 'Template disalin', 'document_template.layout_saved' => 'Draf diubah', 'document_template.layout_revised' => 'Revisi dibuat',
        'document_template.layout_activated' => 'Versi dijadikan aktif', 'document_template.layout_archived' => 'Draf diarsipkan', 'document_template.layout_adopted' => 'Format lama dijadikan draf v1',
        'document_template.deactivated' => 'Template diarsipkan', 'document_template.restored' => 'Template dipulihkan', 'document_template.default_set' => 'Dijadikan default', 'document_template.updated' => 'Template diperbarui (alur lama)',
    ];
    $mark = ['del' => ['−', 'Dihapus'], 'ins' => ['+', 'Ditambahkan']];
    // Isi versi selalu lewat e(): pewarnaan memakai <mark> dari daftar tetap, nilai tidak pernah dirender sebagai HTML.
    $html = fn (array $parts) => collect($parts)->map(fn ($p) => $p['t'] === 'eq' ? e($p['v']) : '<mark class="is-'.($p['t'] === 'del' ? 'del' : 'ins').'">'.e($p['v']).'</mark>')->implode('');
    $activeV = collect($versions)->firstWhere('status', 'active');
    $defaultA = $activeV['v'] ?? ($versions[1]['v'] ?? ($versions[0]['v'] ?? null));
@endphp

@if ($versions === [])
    <p class="bd-ix__muted">Template ini belum punya versi format cetak. Buka tab Format untuk memulainya.</p>
@else
    <ol class="bd-ix__vlist bd-dp__vlist">
        @foreach ($versions as $v)
            <li>
                <a class="bd-ix__vrow {{ $selected && $v['v'] === $selected['v'] ? 'is-selected' : '' }}" href="{{ $vurl($v) }}" data-dp-nav>
                    <span class="bd-ix__vnum">v{{ $v['v'] }}</span>
                    <span class="bd-ix__vstate bd-ix__vstate--{{ $v['status'] }}">{{ $vlabel($v) }}</span>
                    <span class="bd-ix__vmeta">{{ $wibShort($v['created_at']) }}@if ($v['note']) · {{ $v['note'] }}@endif</span>
                </a>
            </li>
        @endforeach
    </ol>

    @if (count($versions) > 1)
        <form method="GET" action="{{ route('admin.document-templates.edit', $template) }}" class="bd-ix__compare bd-dp__compare" data-dp-nav-form>
            <input type="hidden" name="tab" value="versi">
            <h3 class="bd-ix__side-title">Bandingkan</h3>
            <div class="bd-ix__pair">
                <select class="bd-ix__select" name="a" aria-label="Versi pembanding (lama)">
                    @foreach ($versions as $v)<option value="{{ $v['v'] }}" @selected($v['v'] === ($compare['old']['v'] ?? $defaultA))>v{{ $v['v'] }} · {{ $vlabel($v) }}</option>@endforeach
                </select>
                <span aria-hidden="true">→</span>
                <select class="bd-ix__select" name="b" aria-label="Versi pembanding (baru)">
                    @foreach ($versions as $v)<option value="{{ $v['v'] }}" @selected($v['v'] === ($compare['new']['v'] ?? ($selected['v'] ?? null)))>v{{ $v['v'] }} · {{ $vlabel($v) }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="bd-ix__btn bd-ix__btn--small">Bandingkan</button>
        </form>
    @endif

    @if ($compare)
        @php $changed = collect($compare['fields'])->where('changed', true); @endphp
        <section class="bd-dp__diff" aria-label="Perbandingan versi">
            <h3 class="bd-ix__vtitle">v{{ $compare['old']['v'] }} <span aria-hidden="true">→</span><span class="bd-ix__sr"> dibandingkan dengan </span> v{{ $compare['new']['v'] }}</h3>
            <p class="bd-ix__muted">Hanya membaca: membandingkan tidak mengubah data apa pun.</p>
            @forelse ($changed as $field)
                <section class="bd-ix__diff" aria-label="Perubahan pada {{ $field['field'] }}">
                    <h4>{{ $field['field'] }}</h4>
                    <div class="bd-ix__diff-body" tabindex="0">
                        @foreach ($field['rows'] as $row)
                            @if ($row['t'] === 'skip')
                                <p class="bd-ix__diff-skip">… {{ $row['count'] }} baris tidak berubah</p>
                            @else
                                <div class="bd-ix__line is-{{ $row['t'] }}">
                                    @if ($row['t'] !== 'eq')
                                        <span class="bd-ix__sign" aria-hidden="true">{{ $mark[$row['t']][0] }}</span>
                                        <span class="bd-ix__sr">{{ $mark[$row['t']][1] }}: </span>
                                    @endif
                                    <span class="bd-ix__line-text">{!! $html($row['parts']) !!}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </section>
            @empty
                <p class="bd-ix__muted">Pengaturan kedua versi identik.</p>
            @endforelse
        </section>
    @endif
@endif

<section aria-labelledby="pf-hist">
    <h3 id="pf-hist" class="bd-ix__side-title">Riwayat perubahan template ini</h3>
    @if ($history->isEmpty())
        <p class="bd-ix__muted">Belum ada catatan perubahan.</p>
    @else
        <ol class="bd-ix__log">
            @foreach ($history as $entry)
                <li>
                    <span>{{ $labels[$entry->action] ?? $entry->action }}@if (! empty($entry->metadata_json['layout_version'])) <strong>v{{ $entry->metadata_json['layout_version'] }}</strong>@endif @if (! empty($entry->metadata_json['previous_version'])), menggantikan v{{ $entry->metadata_json['previous_version'] }}@endif @if (! empty($entry->metadata_json['from_version'])), dari v{{ $entry->metadata_json['from_version'] }}@endif</span>
                    <small>{{ $entry->user?->name ?? 'Sistem' }} · {{ $wibShort($entry->created_at?->toIso8601String()) }}</small>
                </li>
            @endforeach
        </ol>
    @endif
</section>
