@php
    $sectionByKey = $sections->keyBy('key');
    $words = function ($s) {
        $parts = [];
        $f = fn ($n) => number_format($n, 0, ',', '.');

        if ($s->min_words && $s->max_words) {
            $parts[] = $f($s->min_words).'–'.$f($s->max_words).' kata';
        } elseif ($s->min_words) {
            $parts[] = 'Minimal '.$f($s->min_words).' kata';
        } elseif ($s->max_words) {
            $parts[] = 'Maksimal '.$f($s->max_words).' kata';
        }

        if ($s->target_words) {
            $parts[] = 'target '.$f($s->target_words);
        }

        if ($s->item_count) {
            $parts[] = $s->item_count.' butir';
        }

        return $parts ?: ['Tanpa batas kata'];
    };
    $included = $sections->filter(fn ($s) => $s->is_enabled && ! ($s->role === 'qa' && $template->qa_mode === 'none'));
@endphp
<div class="bd-ix__dialog-body bd-dp__preview-body">
    <div class="bd-ix__dialog-head">
        <div>
            <h2 id="dp-preview-title">Pratinjau struktur</h2>
            <p>{{ $template->name }} · {{ $included->count() }} bagian akan ditulis. Ini hanya struktur: tidak ada naskah contoh dan tidak ada panggilan AI.</p>
        </div>
        <button type="button" class="bd-ix__icon-btn" data-dp-close aria-label="Tutup pratinjau">@include('admin.tools.partials.icon', ['name' => 'x'])</button>
    </div>

    @if ($sections->isEmpty())
        <p class="bd-ix__muted">Belum ada bagian.</p>
    @else
        <ol class="bd-dp__outline">
            @foreach ($sections as $i => $s)
                @php
                    $info = $sectionInfo[$s->id];
                    $excluded = $s->role === 'qa' && $template->qa_mode === 'none';
                @endphp
                <li class="{{ ($s->is_enabled && ! $excluded) ? '' : 'is-off' }}">
                    <span class="bd-dp__num">{{ $i + 1 }}.</span>
                    <div>
                        <strong>{{ $s->title }}</strong>
                        <p class="bd-ix__sub">
                            @if (! $s->is_enabled) Nonaktif, tidak ikut ditulis
                            @elseif ($excluded) Tidak digunakan (Q&amp;A diatur "Tidak dipakai")
                            @else {{ $s->is_required ? 'Wajib' : 'Opsional' }} @if ($s->role === 'qa') · penempatan: {{ strtolower($qaModes[$template->qa_mode]) }} @endif
                            @endif
                        </p>
                        <p>{{ implode(' · ', $words($s)) }}@if ($s->min_sources) · minimal {{ $s->min_sources }} sumber @endif</p>
                        @if ($s->depends_on)
                            <p class="bd-ix__sub">Membutuhkan: {{ collect($s->depends_on)->map(fn ($k) => $sectionByKey[$k]->title ?? $k)->implode(', ') }}</p>
                        @endif
                        <p class="bd-dp__ver {{ $info['active'] ? 'is-active' : ($info['draft'] ? 'is-draft' : 'is-none') }}">
                            @if ($info['active']) Instruksi v{{ $info['active']->version }} aktif @elseif ($info['draft']) Draf v{{ $info['draft']->version }}, belum aktif @else Belum ada instruksi @endif
                        </p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    <p class="bd-ix__sub">Status: {{ $state['label'] }}. @if ($readiness['issues']) {{ count($readiness['issues']) }} hal perlu dilengkapi. @endif Hasil tulisan nanti tetap diperiksa terhadap batas kata; menyimpan batas di sini tidak menjamin AI memenuhinya.</p>
    <div class="bd-ix__dialog-actions"><button type="button" class="bd-ix__btn" data-dp-close>Tutup</button></div>
</div>
