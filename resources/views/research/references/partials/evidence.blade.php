@php
    use App\Services\Evidence\Evidence;
    $sum = $bundle['summary'];
    $chip = [Evidence::FULL_TEXT => 'is-full', Evidence::ABSTRACT => 'is-abstract', Evidence::METADATA_ONLY => 'is-meta'];
@endphp
<section class="refs-evp" aria-labelledby="refs-evp-title" data-refs-reveal>
    <div class="refs-section-head">
        <h2 id="refs-evp-title">Bukti yang tersedia</h2>
        <p>{{ $sum['text'] }} Urutan tetap menurut relevansi; ketersediaan teks tidak mengubah peringkat.</p>
    </div>

    @if($sum['sources'] > 0)
        <ul class="refs-evp__counts" aria-label="Ringkasan ketersediaan bukti">
            <li class="is-full"><strong>{{ $sum['full_text'] }}</strong> Teks lengkap terindeks</li>
            <li class="is-abstract"><strong>{{ $sum['abstract'] }}</strong> Abstrak tersedia</li>
            <li class="is-meta"><strong>{{ $sum['metadata_only'] }}</strong> Hanya metadata</li>
        </ul>
    @endif

    @if($bundle['limitations'])
        <div class="refs-evp__limits" role="note">
            <h3>Keterbatasan bukti</h3>
            <ul>@foreach($bundle['limitations'] as $l)<li>{{ $l }}</li>@endforeach</ul>
        </div>
    @endif

    @if($sum['sources'] > 0 && $sum['evidence_items'] === 0)
        <p class="refs-evp__empty" role="status">Belum ada teks bukti yang dapat dikutip: semua sumber di atas hanya berisi metadata. Informasi bibliografi tetap bisa dirujuk, tetapi bukan metode, hasil, atau kesimpulan.</p>
    @endif

    <ol class="refs-evp__list">
        @foreach($bundle['selected_sources'] as $s)
            @php($items = array_values(array_filter($bundle['evidence_items'], fn ($i) => $i['source_id'] === $s['source_id'])))
            <li class="refs-evp__source">
                <div class="refs-evp__head">
                    <span class="refs-evp__rank" aria-hidden="true">{{ $s['rank'] }}</span>
                    <div>
                        <h3>{{ \Illuminate\Support\Str::limit($s['title'], 130) }}</h3>
                        <p class="refs-muted">
                            Skor relevansi {{ $s['relevance_score'] !== null ? number_format($s['relevance_score'], 1) : '—' }}/100
                            · {{ $s['kind'] === 'internal' ? 'Pustaka Internal' : implode(', ', array_map(fn ($p) => ['openalex' => 'OpenAlex', 'crossref' => 'Crossref', 'doaj' => 'DOAJ'][$p] ?? ucfirst($p), $s['providers'])) }}
                            · {{ $s['doi'] ? 'DOI '.$s['doi'] : 'Tanpa DOI' }}
                        </p>
                    </div>
                </div>
                <p class="refs-evp__meta">
                    <span class="refs-evidence {{ $chip[$s['evidence_level']] }}">{{ $s['evidence_label'] }}</span>
                    <span class="refs-muted">{{ $s['claim_support_label'] }}.</span>
                    @if($s['pages'])<span class="refs-muted">Halaman bukti: {{ implode('; ', $s['pages']) }}.</span>@endif
                </p>
                @if($s['note'])<p class="refs-muted" role="note">{{ $s['note'] }}</p>@endif
                <p class="refs-evp__cite">Sitasi sumber: <code>{{ $s['source_id'] }}</code></p>

                @if($items)
                    <details class="refs-evp__details">
                        <summary>Lihat bukti ({{ count($items) }})</summary>
                        <ul class="refs-evp__items">
                            @foreach($items as $i)
                                <li>
                                    <p class="refs-evp__item-head"><code>{{ $i['citation_id'] }}</code>
                                        <span>{{ Evidence::pageLabel($i['page_start'], $i['page_end']) ?? ($i['evidence_level'] === Evidence::ABSTRACT ? 'abstrak' : 'halaman tidak diketahui') }}</span>
                                        @if($i['section'] && $i['evidence_level'] === Evidence::FULL_TEXT)<span class="refs-muted">bagian: {{ str_replace('_', ' ', $i['section']) }} (label bagian tidak selalu akurat)</span>@endif
                                    </p>
                                    <blockquote class="refs-snippet"><p>{{ $i['snippet'] }}</p></blockquote>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @else
                    <p class="refs-muted refs-evp__nobody">Tidak ada teks bukti dari sumber ini.</p>
                @endif
            </li>
        @endforeach
    </ol>

    @if(! empty($synth))
        <div class="refs-evp__ai" id="refs-synth">
            <h3>Analisis dengan AI</h3>
            @if(session('synth_error'))<p class="refs-alert is-error" role="alert" data-synth-error>{{ session('synth_error') }}</p>@endif
            @if($synth['test_mode'])
                <p class="refs-testmode" role="note"><strong>{{ $synth['label'] }}.</strong> Hasil disusun deterministik dari bukti di atas, bukan analisis AI produksi. Tidak ada permintaan ke layanan AI dan tidak ada biaya.</p>
            @endif
            @if($synth['billed'] && $synth['available'])
                <p class="refs-testmode is-live" role="note"><strong>{{ $synth['label'] }}.</strong> Analisis memanggil layanan AI berbayar. Perkiraan biaya ditampilkan dan konfirmasi diminta sebelum apa pun dikirim.</p>
            @endif
            @if(! $synth['available'])
                <p class="refs-muted" role="status">{{ $synth['reason'] }}</p>
            @elseif($sum['evidence_items'] === 0)
                <p class="refs-muted" role="status">Belum ada teks bukti yang dapat dianalisis. Analisis tidak ditawarkan untuk sumber yang hanya berisi metadata.</p>
            @else
                <form method="POST" action="{{ route('research.references.synthesis') }}" class="refs-evp__form">
                    @csrf
                    <input type="hidden" name="category_id" value="{{ $f['category_id'] }}">
                    <input type="hidden" name="q" value="{{ $f['q'] }}">
                    @foreach(array_filter($f['filters']) as $fk => $fv)<input type="hidden" name="{{ $fk }}" value="{{ $fv }}">@endforeach
                    <label for="refs-synth-tool">Jenis analisis</label>
                    <select id="refs-synth-tool" name="prompt_template_id" required>
                        @foreach($synth['tools'] as $t)<option value="{{ $t['prompt_template_id'] }}">{{ $t['label'] }} (instruksi v{{ $t['version'] }})</option>@endforeach
                    </select>
                    <button type="submit" class="refs-btn refs-btn--primary">Analisis dengan AI</button>
                </form>
                <p class="refs-muted refs-evp__hint">Anda akan diminta mengonfirmasi sebelum analisis dijalankan.</p>
            @endif
        </div>
    @endif
</section>
