@php
    $in = $previewInput;
    $r = $preview['resolved'] ?? null;
    $scopeLabel = fn ($s) => $scopes[$s] ?? $s;
@endphp

<p class="bd-ab__banner" role="note"><strong>Pratinjau konfigurasi — bukan hasil AI.</strong> Halaman ini hanya membaca aturan dan menunjukkan apa yang akan dikirim ke model atau ditampilkan sistem. Tidak ada panggilan AI, biaya, atau perubahan data.</p>

<form method="GET" action="{{ route('admin.answer-behavior.show', $rule['uuid']) }}" class="bd-ab__previewform" data-dp-nav-form>
    <input type="hidden" name="tab" value="pratinjau">
    <input type="hidden" name="v" value="{{ $version->id }}">
    <div class="bd-ix__grid bd-dp__grid3">
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="pv-uji">Aturan yang diuji</label>
            <select class="bd-ix__input bd-ix__select" id="pv-uji" name="uji">
                <option value="this" @selected($in['uji'] === 'this')>Versi ini (v{{ $version->version }}), seolah aktif</option>
                <option value="active" @selected($in['uji'] === 'active')>Hanya yang sekarang aktif</option>
            </select>
        </div>
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="pv-mode">Mode</label>
            <select class="bd-ix__input bd-ix__select" id="pv-mode" name="mode">
                <option value="research" @selected($in['mode'] === 'research')>Riset akademik</option>
                <option value="document" @selected($in['mode'] === 'document')>Penulisan dokumen</option>
            </select>
        </div>
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="pv-alat">Alat riset</label>
            <select class="bd-ix__input bd-ix__select" id="pv-alat" name="alat">
                <option value="">Tanpa alat tertentu</option>
                @foreach ($tools as $code => $name)
                    <option value="{{ $code }}" @selected($in['alat'] === $code)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="bd-ix__grid">
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="pv-kondisi">Kondisi bukti</label>
            <select class="bd-ix__input bd-ix__select" id="pv-kondisi" name="kondisi">
                @foreach ($previewConditions as $value => $label)
                    <option value="{{ $value }}" @selected($in['kondisi'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="pv-contoh">Contoh pertanyaan <small>opsional</small></label>
            <input class="bd-ix__input" id="pv-contoh" name="contoh" maxlength="500" value="{{ $in['contoh'] }}" placeholder="mis. topik yang ingin Anda bayangkan">
        </div>
    </div>
    <div class="bd-ix__actions"><button type="submit" class="bd-ix__btn bd-ix__btn--primary">Tampilkan pratinjau</button></div>
</form>

@if (! $preview)
    <p class="bd-ix__alert" role="alert">Konfigurasi versi ini tidak terbaca, jadi tidak dapat dipratinjau.</p>
@else
    <section class="bd-ab__result" aria-labelledby="pv-applied">
        <h3 id="pv-applied" class="bd-ix__side-title">Aturan yang berlaku untuk kondisi ini</h3>
        @if (! $preview['thisApplied'] && $in['uji'] === 'this')
            <p class="bd-ix__alert" role="status">v{{ $version->version }} tidak berlaku pada mode/alat yang dipilih, atau digantikan aturan yang lebih khusus. Lihat daftar "Digantikan" dan "Tidak berlaku" di bawah.</p>
        @endif
        @if ($r['applied'])
            <ol class="bd-ab__applied">
                @foreach ($r['applied'] as $a)
                    <li>
                        <strong>{{ $a['title'] }}</strong>
                        <span class="bd-ix__sub">{{ $groups[$a['category']]['label'] }} · {{ $scopeLabel($a['scope']) }} · v{{ $a['version'] }} · {{ $a['how'] === 'stack' ? 'menumpuk' : 'dipakai' }}@if ($a['status'] !== 'active') · <em>belum aktif (pratinjau)</em>@endif</span>
                    </li>
                @endforeach
            </ol>
        @else
            <p class="bd-ix__muted">Tidak ada aturan aktif yang cocok. AI memakai perilaku bawaan sistem: prompt dan skema keluaran tidak berubah.</p>
        @endif
        @foreach ($r['overridden'] as $o)
            <p class="bd-ix__muted">Digantikan: "{{ $o['title'] }}" ({{ $groups[$o['category']]['label'] }}, {{ $scopeLabel($o['scope']) }}) oleh "{{ $o['by'] }}".</p>
        @endforeach
        @foreach ($r['ignored'] as $ig)
            <p class="bd-ix__muted">Tidak berlaku: "{{ $ig['title'] }}" — {{ $ig['reason'] }}</p>
        @endforeach
        @foreach ($r['warnings'] as $w)
            <p class="bd-ix__alert" role="status">{{ $w }}</p>
        @endforeach
        @if ($in['mode'] === 'document')
            <p class="bd-ix__lock is-draft">@include('admin.tools.partials.icon', ['name' => 'lock']) Mode penulisan dokumen belum dipakai research engine. Konfigurasi ini hanya terbaca resolver.</p>
        @endif
    </section>

    @php $copy = $preview['copy']; $cond = $preview['condition']; @endphp
    @if ($copy)
        <section class="bd-ab__result" aria-labelledby="pv-copy">
            <h3 id="pv-copy" class="bd-ix__side-title">Pesan yang tampil: {{ $conditions[$cond] }}</h3>
            <p class="bd-ab__note">Ditampilkan sistem tanpa memanggil AI. {{ $cond === 'error' ? 'Ini error teknis, bukan berarti literatur tidak ada. Detail error tidak pernah ditampilkan ke pengguna.' : 'Ini kondisi sumber, bukan error teknis.' }}</p>
            <blockquote class="bd-ab__sample">
                <p>{{ $copy['message'] }}</p>
                <p class="bd-ix__sub">Langkah berikutnya: {{ $copy['next_action'] }}</p>
            </blockquote>
            <p class="bd-ix__muted">{{ $copy['customised'] ? 'Memakai teks dari aturan: '.collect([$preview['resolved']['unavailable'][$cond]['message_from'] ?? null, $preview['resolved']['unavailable'][$cond]['next_action_from'] ?? null])->filter()->unique()->implode(', ').'.' : 'Memakai teks bawaan sistem (tidak ada aturan yang menulis pesan ini).' }}</p>
        </section>
    @elseif ($cond === 'conflict')
        <section class="bd-ab__result" aria-labelledby="pv-copy">
            <h3 id="pv-copy" class="bd-ix__side-title">Sumber bertentangan</h3>
            <p class="bd-ab__note">Sistem tidak dapat mendeteksi pertentangan sumber secara otomatis. Penanganannya diserahkan ke model lewat arahan di bawah, jadi hasilnya tidak dijamin.</p>
            <p>{{ $r['grounding']['conflict'] ? 'Aturan aktif meminta model menampilkan perbedaan pandangan dan sumber pendukungnya, tanpa konsensus palsu.' : 'Tidak ada aturan aktif yang meminta penanganan khusus untuk pertentangan sumber.' }}</p>
        </section>
    @endif

    @if (in_array($cond, ['ok', 'conflict'], true))
        <section class="bd-ab__result" aria-labelledby="pv-dir">
            <h3 id="pv-dir" class="bd-ix__side-title">Arahan yang dikirim ke model</h3>
            @if ($preview['payload'])
                <p class="bd-ab__note">Disisipkan di prompt sebelum "OUTPUT REQUIREMENTS" dan selalu di bawah SECURITY AND EVIDENCE RULES. Admin saja yang melihat ini; tidak pernah ditampilkan ke pengguna.</p>
                <pre class="bd-ix__prompt" tabindex="0">{{ $preview['payload']['directive'] }}</pre>
                <dl class="bd-ab__dl">
                    <div><dt>Bahasa jawaban</dt><dd>{{ $preview['payload']['language'] ? ($languages[$preview['payload']['language']] ?? $preview['payload']['language']) : 'Bawaan (Indonesia, kecuali pengguna meminta lain)' }}</dd></div>
                    <div><dt>Pertanyaan lanjutan</dt><dd>
                        @if ($preview['payload']['follow_up'])
                            {{ $preview['payload']['follow_up']['min'] }}–{{ $preview['payload']['follow_up']['max'] }} pertanyaan, jenis: {{ collect($preview['payload']['follow_up']['types'])->map(fn ($k) => $followTypes[$k] ?? $k)->implode(', ') }}. Model mengisi field <code>follow_ups</code> pada respons yang sama (tanpa panggilan kedua); server membatasi jumlah, membuang duplikat, dan menyembunyikannya bila bukti kurang dari {{ $preview['payload']['follow_up']['hide_below_evidence'] }}.
                        @else
                            Tidak diminta: skema keluaran provider tidak memuat field itu.
                        @endif
                    </dd></div>
                </dl>
            @else
                <p class="bd-ix__muted">Tidak ada arahan: payload dan skema keluaran sama persis dengan perilaku bawaan.</p>
            @endif
            @if ($preview['sample'] !== '')
                <h4 class="bd-ix__side-title">Kerangka prompt dengan contoh pertanyaan</h4>
                <ol class="bd-ab__skeleton">
                    <li>RESEARCH TOOL + TASK (isi prompt alat dari Instruksi &amp; Versi)</li>
                    <li>USER QUESTION: <em>{{ $preview['sample'] }}</em></li>
                    <li>CONVERSATION CONTEXT dan GLOSSARY CONTEXT (bukan bukti)</li>
                    <li>EVIDENCE: potongan sumber Pustaka yang diambil saat riset berjalan</li>
                    <li>ANSWER BEHAVIOR POLICY: {{ $preview['payload'] ? 'arahan di atas' : '(kosong)' }}</li>
                    <li>OUTPUT REQUIREMENTS</li>
                </ol>
            @endif
        </section>
    @endif

    <p class="bd-ix__muted">Menguji respons AI nyata tidak tersedia di halaman ini: itu memerlukan provider terkonfigurasi dan dapat berbiaya, jadi tidak dijalankan otomatis.</p>
@endif
