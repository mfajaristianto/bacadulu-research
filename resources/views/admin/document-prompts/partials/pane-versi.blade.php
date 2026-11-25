@php
    $wibShort = fn ($date) => $date?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i');
    $vlabel = fn ($v) => ['active' => 'Sedang aktif', 'draft' => 'Draf', 'archived' => 'Diarsipkan'][$v->status] ?? $v->status;
    $vurl = fn ($v) => route('admin.prompt-documents.show', ['template' => $template->id, 'bagian' => $selected->key, 'v' => $v->id]);
    $actionLabels = [
        'document_prompt.prompt.created' => 'Draf v1 dibuat', 'document_prompt.prompt.updated' => 'Draf diubah', 'document_prompt.prompt.revised' => 'Revisi dibuat',
        'document_prompt.prompt.activated' => 'Dijadikan aktif', 'document_prompt.prompt.archived' => 'Draf diarsipkan', 'document_prompt.section.created' => 'Bagian ditambahkan',
        'document_prompt.section.updated' => 'Konfigurasi diubah', 'document_prompt.section.moved' => 'Urutan diubah',
    ];
    $log = $history->filter(fn ($h) => ($h->metadata_json['section'] ?? null) === $selected->key);
    $defaultA = ($versions->firstWhere('id', $selected->active_prompt_template_id) ?? $versions->skip(1)->first() ?? $versions->first())?->id;
    $mark = ['del' => ['−', 'Dihapus'], 'ins' => ['+', 'Ditambahkan']];
    // Isi versi selalu lewat e(): pewarnaan memakai <mark> dari daftar tetap, prompt tidak pernah dirender sebagai HTML.
    $html = fn (array $parts) => collect($parts)->map(fn ($p) => $p['t'] === 'eq' ? e($p['v']) : '<mark class="is-'.($p['t'] === 'del' ? 'del' : 'ins').'">'.e($p['v']).'</mark>')->implode('');
@endphp

@if ($versions->isEmpty())
    <p class="bd-ix__muted">Belum ada versi. Tulis instruksi bagian ini di tab Instruksi.</p>
@else
    <ol class="bd-ix__vlist bd-dp__vlist">
        @foreach ($versions as $v)
            <li>
                <a class="bd-ix__vrow {{ $version && $v->id === $version->id ? 'is-selected' : '' }}" href="{{ $vurl($v) }}" data-dp-nav>
                    <span class="bd-ix__vnum">v{{ $v->version }}</span>
                    <span class="bd-ix__vstate bd-ix__vstate--{{ $v->status }}">{{ $vlabel($v) }}</span>
                    <span class="bd-ix__vmeta">{{ $wibShort($v->updated_at) }}@if ($v->creator) · {{ $v->creator->name }}@endif @if ($v->ai_runs_count) · dipakai {{ $v->ai_runs_count }} pekerjaan @endif</span>
                </a>
            </li>
        @endforeach
    </ol>

    @if ($versions->count() > 1)
        <form method="GET" action="{{ route('admin.prompt-documents.show', $template->id) }}" class="bd-ix__compare bd-dp__compare" data-dp-nav-form>
            <input type="hidden" name="bagian" value="{{ $selected->key }}">
            <input type="hidden" name="tab" value="versi">
            <h3 class="bd-ix__side-title">Bandingkan</h3>
            <div class="bd-ix__pair">
                <select class="bd-ix__select" name="a" aria-label="Versi pembanding (lama)">
                    @foreach ($versions as $v)
                        <option value="{{ $v->id }}" @selected($v->id === ($compare['old']->id ?? $defaultA))>v{{ $v->version }} · {{ $vlabel($v) }}</option>
                    @endforeach
                </select>
                <span aria-hidden="true">→</span>
                <select class="bd-ix__select" name="b" aria-label="Versi pembanding (baru)">
                    @foreach ($versions as $v)
                        <option value="{{ $v->id }}" @selected($v->id === ($compare['new']->id ?? $version?->id))>v{{ $v->version }} · {{ $vlabel($v) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bd-ix__btn bd-ix__btn--small">Bandingkan</button>
        </form>
    @endif

    @if ($compare)
        @php $changed = collect($compare['fields'])->where('changed', true); @endphp
        <section class="bd-dp__diff" aria-label="Perbandingan versi">
            <h3 class="bd-ix__vtitle">v{{ $compare['old']->version }} <span aria-hidden="true">→</span><span class="bd-ix__sr"> dibandingkan dengan </span> v{{ $compare['new']->version }}</h3>
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
                <p class="bd-ix__muted">Isi kedua versi identik.</p>
            @endforelse
        </section>
    @endif
@endif

<section aria-labelledby="dp-hist">
    <h3 id="dp-hist" class="bd-ix__side-title">Riwayat perubahan bagian ini</h3>
    @if ($log->isEmpty())
        <p class="bd-ix__muted">Belum ada catatan perubahan.</p>
    @else
        <ol class="bd-ix__log">
            @foreach ($log as $entry)
                <li>
                    <span>{{ $actionLabels[$entry->action] ?? $entry->action }}@if (! empty($entry->metadata_json['version'])) <strong>v{{ $entry->metadata_json['version'] }}</strong>@endif @if (! empty($entry->metadata_json['previous_version'])), menggantikan v{{ $entry->metadata_json['previous_version'] }}@endif</span>
                    <small>{{ $entry->user?->name ?? 'Sistem' }} · {{ $wibShort($entry->created_at) }}</small>
                </li>
            @endforeach
        </ol>
    @endif
</section>
