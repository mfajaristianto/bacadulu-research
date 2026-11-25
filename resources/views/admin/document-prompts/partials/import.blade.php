@php $labels = ['task_prompt' => 'Isi prompt', 'system_prompt' => 'Peran dan batasan', 'retrieval_instructions' => 'Penggunaan sumber', 'output_instructions' => 'Format keluaran']; @endphp
<div class="bd-dp__import">
    <section class="bd-dp__import-head" aria-labelledby="dp-imp-title">
        <h2 id="dp-imp-title">{{ $preset['name'] }}</h2>
        <p class="bd-ix__muted">{{ $preset['description'] }}</p>
        <dl class="bd-ix__facts">
            <div><dt>Jenis</dt><dd>{{ $types[$preset['document_type']] }}</dd></div>
            <div><dt>Bahasa dan sitasi</dt><dd>{{ strtoupper($preset['language']) }} · {{ $preset['citation_style'] }}</dd></div>
            <div><dt>Asal isi</dt><dd>{{ $preset['origin'] }}</dd></div>
            <div><dt>Status saat disimpan</dt><dd>Draf. Tidak ada versi atau template yang diaktifkan otomatis.</dd></div>
        </dl>
        @if (! empty($preset['example']))
            <p class="bd-ix__muted"><strong>Contoh proyek (metadata, bukan isi prompt):</strong> “{{ $preset['example']['title'] }}” — {{ $preset['example']['research_question'] }}</p>
        @endif

        @if ($existing)
            <p class="bd-ix__lock">@include('admin.tools.partials.icon', ['name' => 'lock']) Template ini sudah tersimpan. <a class="bd-ix__link" href="{{ route('admin.prompt-documents.show', $existing->id) }}" data-dp-nav>Buka template yang ada</a> atau salin untuk membuat varian.</p>
        @else
            <form method="POST" action="{{ route('admin.prompt-documents.import') }}" class="bd-ix__actions" data-dp-form data-dp-kind="import">
                @csrf
                <input type="hidden" name="preset" value="{{ $preset['key'] }}">
                <p class="bd-ix__alert" data-dp-error role="alert" hidden></p>
                <a href="{{ route('admin.prompt-documents.index', ['baru' => 1]) }}" class="bd-ix__btn bd-ix__btn--quiet" data-dp-nav>Batal</a>
                <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-save>Simpan sebagai Draf</button>
            </form>
        @endif
    </section>

    <ol class="bd-dp__import-sections" aria-label="Bagian preset">
        @foreach ($preset['sections'] as $i => $s)
            <li>
                <details @if ($i === 0) open @endif>
                    <summary>
                        <span class="bd-dp__num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <strong>{{ $s['title'] }}</strong>
                        <span class="bd-ix__sub">{{ $s['is_required'] ? 'Wajib' : 'Opsional' }}@if ($s['min_words']) · min {{ number_format($s['min_words'], 0, ',', '.') }} kata @endif @if ($s['max_words']) · maks {{ number_format($s['max_words'], 0, ',', '.') }} kata @endif @if ($s['item_count']) · {{ $s['item_count'] }} butir @endif</span>
                    </summary>
                    <div class="bd-dp__import-body">
                        <p class="bd-ix__muted">{{ $s['purpose'] }}</p>
                        @if ($s['depends_on'])
                            <p class="bd-ix__muted">Bergantung pada: {{ collect($s['depends_on'])->map(fn ($k) => collect($preset['sections'])->firstWhere('key', $k)['title'] ?? $k)->implode(', ') }}</p>
                        @endif
                        @foreach ($labels as $field => $label)
                            @if (filled($s['prompt'][$field] ?? null))
                                <h4>{{ $label }} <small>{{ $field === 'task_prompt' ? 'dari berkas Fajar dan rincian admin' : 'aturan tambahan dari spesifikasi admin' }}</small></h4>
                                <pre class="bd-ix__prompt" tabindex="0">{{ $s['prompt'][$field] }}</pre>
                            @endif
                        @endforeach
                    </div>
                </details>
            </li>
        @endforeach
    </ol>

    <section aria-labelledby="dp-imp-ph">
        <h3 id="dp-imp-ph" class="bd-ix__side-title">Variabel yang dipakai</h3>
        <p class="bd-ix__muted">Topik, judul, dan rumusan masalah selalu berupa variabel, jadi preset ini bisa dipakai untuk topik riset apa pun.</p>
        <p class="bd-dp__chips">@foreach ($placeholders as $name => [$label]) <code>{{ '{'.$name.'}' }}</code> @endforeach</p>
    </section>
</div>
