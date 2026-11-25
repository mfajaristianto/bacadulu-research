@php
    $changedFields = collect($compare['fields'])->where('changed', true);
    $same = count($compare['fields']) - $changedFields->count();
    $mark = ['del' => ['−', 'Dihapus'], 'ins' => ['+', 'Ditambahkan']];
    // Teks versi selalu lewat e(): pewarnaan memakai <mark> dari daftar tetap, isi prompt tidak pernah dirender sebagai HTML.
    $html = fn (array $parts) => collect($parts)->map(fn ($p) => $p['t'] === 'eq' ? e($p['v']) : '<mark class="is-'.($p['t'] === 'del' ? 'del' : 'ins').'">'.e($p['v']).'</mark>')->implode('');
@endphp
<header class="bd-ix__vhead">
    <div>
        <h3 class="bd-ix__vtitle">v{{ $compare['old']->version }} <span aria-hidden="true">→</span><span class="bd-ix__sr"> dibandingkan dengan </span> v{{ $compare['new']->version }}</h3>
        <p class="bd-ix__muted">Hanya membaca: membandingkan tidak mengubah data apa pun.{{ $same ? ' '.$same.' bagian tidak berubah dan disembunyikan.' : '' }}</p>
    </div>
    <a class="bd-ix__btn bd-ix__btn--quiet" href="{{ route('admin.prompts.index', ['instruksi' => $selected->id, 'v' => $selected->id]) }}" data-ix-nav>Tutup perbandingan</a>
</header>

@forelse ($changedFields as $field)
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
