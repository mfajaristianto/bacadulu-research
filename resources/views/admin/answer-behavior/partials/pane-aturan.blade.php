@php
    $wibShort = fn ($date) => $date?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i');
    $vlabel = fn ($v) => ['active' => 'Aktif', 'draft' => 'Draf', 'archived' => 'Diarsipkan'][$v->status] ?? $v->status;
    $vurl = fn ($v) => route('admin.answer-behavior.show', ['rule' => $rule['uuid'], 'v' => $v->id]);
    $active = $rule['active'];
    $editable = ! $lock && $config !== [];
    $group = $config['group'] ?? null;
    $max = \App\Services\Research\AnswerBehavior::MAX_TEXT;
    $on = fn ($v) => $v ? 'checked' : '';
@endphp

<div class="bd-dp__vbar">
    <ol class="bd-dp__vchips" aria-label="Versi aturan {{ $rule['title'] }}">
        @foreach ($versions as $v)
            <li><a href="{{ $vurl($v) }}" data-dp-nav class="bd-dp__vchip bd-dp__vchip--{{ $v->status }} {{ $v->id === $version->id ? 'is-on' : '' }}" @if ($v->id === $version->id) aria-current="true" @endif>v{{ $v->version }} <span>{{ $vlabel($v) }}</span></a></li>
        @endforeach
    </ol>
    <a class="bd-ix__link" href="{{ route('admin.answer-behavior.show', ['rule' => $rule['uuid'], 'tab' => 'versi', 'v' => $version->id]) }}" data-dp-nav>Riwayat dan perbandingan</a>
</div>

<header class="bd-dp__vhead">
    <div>
        <h3 class="bd-ix__vtitle">v{{ $version->version }} <span class="bd-ix__vstate bd-ix__vstate--{{ $version->status }}">{{ $vlabel($version) }}</span></h3>
        <p class="bd-ix__muted">Diperbarui {{ $wibShort($version->updated_at) }}@if ($version->creator) · oleh {{ $version->creator->name }}@endif</p>
    </div>
    <div class="bd-ix__vactions">
        @if ($version->status === 'active')
            <span class="bd-ix__active-tag">@include('admin.tools.partials.icon', ['name' => 'check']) Sedang aktif</span>
        @else
            <form method="POST" action="{{ route('admin.answer-behavior.activate', [$rule['uuid'], $version->id]) }}" data-dp-form data-dp-kind="activate" data-dp-ask-title="Jadikan v{{ $version->version }} aktif?" data-dp-ask-text="{{ $active ? 'v'.$active->version.' akan diarsipkan (tetap tersimpan) dan v'.$version->version.' menjadi yang dipakai.' : 'Aturan ini mulai dipakai.' }} Berlaku untuk riset berikutnya; hasil riset lama tidak ditulis ulang." data-dp-ask-ok="Jadikan aktif">
                @csrf
                <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-activate @disabled($config === [])>Jadikan Aktif</button>
            </form>
        @endif
        <form method="POST" action="{{ route('admin.answer-behavior.revise', [$rule['uuid'], $version->id]) }}" data-dp-form data-dp-ask-title="Buat revisi dari v{{ $version->version }}?" data-dp-ask-text="Draf baru dibuat dari v{{ $version->version }}. Versi aktif tidak berubah." data-dp-ask-ok="Buat revisi">
            @csrf
            <button type="submit" class="bd-ix__btn" data-dp-revise>Buat Revisi</button>
        </form>
        @if ($version->status === 'draft')
            <form method="POST" action="{{ route('admin.answer-behavior.archive', [$rule['uuid'], $version->id]) }}" data-dp-form data-dp-ask-title="Arsipkan draf v{{ $version->version }}?" data-dp-ask-text="Isinya tetap tersimpan dan bisa dijadikan aktif atau direvisi lagi nanti." data-dp-ask-ok="Arsipkan draf">
                @csrf
                <button type="submit" class="bd-ix__btn bd-ix__btn--quiet">Arsipkan draf</button>
            </form>
        @endif
    </div>
</header>

@if ($lock)
    <p class="bd-ix__lock">@include('admin.tools.partials.icon', ['name' => 'lock']) {{ $lock }}</p>
@else
    <p class="bd-ix__lock is-draft">Draf ini belum dipakai riset mana pun. Perubahan disimpan sebagai draf dan tidak mengubah versi aktif.</p>
@endif

@if ($config === [])
    <p class="bd-ix__alert" role="alert">Konfigurasi versi ini tidak dapat dibaca, jadi tidak bisa diaktifkan. Buka versi lain atau buat revisi dari versi yang sehat.</p>
@endif

@if ($conflict)
    <p class="bd-ix__alert" role="status">Aturan "{{ $conflict['title'] }}" sudah aktif untuk kelompok dan cakupan yang sama. <a class="bd-ix__link" href="{{ route('admin.answer-behavior.show', $conflict['uuid']) }}" data-dp-nav>Buka aturan itu</a>. Sebelum versi ini bisa aktif, aturan tersebut harus dinonaktifkan.</p>
@endif

@if ($lintNotes)
    <p class="bd-ix__alert" role="alert">Isi prompt ini tidak dapat diaktifkan: {{ $lintNotes[0] }}</p>
@endif

@if ($editable)
    <form method="POST" action="{{ route('admin.answer-behavior.update', [$rule['uuid'], $version->id]) }}" class="bd-ix__form" novalidate data-dp-form data-dp-dirty data-dp-kind="prompt">
        @csrf @method('PUT')
        <div class="bd-ix__fields">
            <div class="bd-ix__field">
                <label class="bd-ix__label" for="ab-title">Nama aturan <small>wajib</small></label>
                <input class="bd-ix__input" id="ab-title" name="title" maxlength="120" value="{{ $version->indonesian_name }}" required data-dp-field>
                <p class="bd-ix__note" data-error-for="title"></p>
            </div>
            <div class="bd-ix__field">
                <label class="bd-ix__label" for="ab-desc">Deskripsi <small>opsional</small></label>
                <textarea class="bd-ix__input bd-ix__textarea" id="ab-desc" name="description" rows="2" maxlength="400" data-dp-field>{{ $version->short_description }}</textarea>
                <p class="bd-ix__note" data-error-for="description"></p>
            </div>
            <div class="bd-ix__grid">
                <div class="bd-ix__field">
                    <label class="bd-ix__label" for="ab-scope">Cakupan</label>
                    <select class="bd-ix__input bd-ix__select" id="ab-scope" name="scope" data-dp-field aria-describedby="ab-scope-h">
                        @foreach ($scopes as $value => $label)
                            <option value="{{ $value }}" @selected($config['scope'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="bd-ix__note" data-error-for="scope"></p>
                </div>
                <div class="bd-ix__field">
                    <label class="bd-ix__label" for="ab-tool">Alat <small>hanya untuk cakupan alat tertentu</small></label>
                    <select class="bd-ix__input bd-ix__select" id="ab-tool" name="tool_code" data-dp-field>
                        <option value="">—</option>
                        @foreach ($tools as $code => $name)
                            <option value="{{ $code }}" @selected(($config['tool_code'] ?? null) === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="bd-ix__note" data-error-for="tool_code"></p>
                </div>
            </div>
            <p class="bd-ix__hint" id="ab-scope-h">Aturan yang lebih khusus (alat, lalu mode) menggantikan aturan umum untuk kelompok yang sama. Kelompok sumber selalu menumpuk dan tidak dapat dilemahkan.</p>

            <fieldset class="bd-dp__fieldset bd-ab__group">
                <legend>{{ $groups[$group]['label'] }}</legend>
                <p class="bd-ix__hint">{{ $groups[$group]['hint'] }}</p>

                @if ($group === 'style')
                    @php $s = $config['style']; @endphp
                    <div class="bd-ix__grid bd-dp__grid3">
                        <div class="bd-ix__field">
                            <label class="bd-ix__label" for="ab-depth">Kedalaman</label>
                            <select class="bd-ix__input bd-ix__select" id="ab-depth" name="depth" data-dp-field aria-describedby="ab-depth-h">
                                @foreach ($depths as $value => $label)<option value="{{ $value }}" @selected($s['depth'] === $value)>{{ $label }}</option>@endforeach
                            </select>
                            <p class="bd-ix__note" data-error-for="depth"></p>
                        </div>
                        <div class="bd-ix__field">
                            <label class="bd-ix__label" for="ab-register">Register bahasa</label>
                            <select class="bd-ix__input bd-ix__select" id="ab-register" name="register" data-dp-field>
                                @foreach ($registers as $value => $label)<option value="{{ $value }}" @selected($s['register'] === $value)>{{ $label }}</option>@endforeach
                            </select>
                            <p class="bd-ix__note" data-error-for="register"></p>
                        </div>
                        <div class="bd-ix__field">
                            <label class="bd-ix__label" for="ab-language">Bahasa jawaban</label>
                            <select class="bd-ix__input bd-ix__select" id="ab-language" name="language" data-dp-field>
                                @foreach ($languages as $value => $label)<option value="{{ $value }}" @selected($s['language'] === $value)>{{ $label }}</option>@endforeach
                            </select>
                            <p class="bd-ix__note" data-error-for="language"></p>
                        </div>
                    </div>
                    <p class="bd-ix__hint" id="ab-depth-h">Kedalaman adalah target gaya penulisan yang diminta dari model, bukan jaminan jumlah kata atau token.</p>
                    <label class="bd-dp__check"><input type="hidden" name="explain_terms" value="0"><input type="checkbox" name="explain_terms" value="1" data-dp-field @checked($s['explain_terms'])> Jelaskan istilah teknis saat pertama muncul</label>
                @elseif ($group === 'structure')
                    @php $picked = $config['structure']['sections']; @endphp
                    <div class="bd-dp__checks" role="group" aria-label="Bagian jawaban">
                        @foreach ($sectionOptions as $value => $label)
                            <label class="bd-dp__check"><input type="checkbox" name="sections[]" value="{{ $value }}" data-dp-field @checked(in_array($value, $picked, true))> {{ $label }}</label>
                        @endforeach
                    </div>
                    <p class="bd-ix__note" data-error-for="sections"></p>
                    <p class="bd-ix__hint">Urutan selalu dari ringkasan ke kesimpulan. Bagian yang tidak didukung bukti dilewati, bukan diisi karangan. Bagian "Referensi" tidak ditulis model: daftar sumber berasal dari sitasi tervalidasi sistem.</p>
                @elseif ($group === 'grounding')
                    @php $g = $config['grounding']; @endphp
                    <div class="bd-dp__checks" role="group" aria-label="Perilaku sumber">
                        <label class="bd-dp__check"><input type="hidden" name="partial" value="0"><input type="checkbox" name="partial" value="1" data-dp-field @checked($g['partial'])> Jawab bagian yang didukung, nyatakan bagian yang belum</label>
                        <label class="bd-dp__check"><input type="hidden" name="conflict" value="0"><input type="checkbox" name="conflict" value="1" data-dp-field @checked($g['conflict'])> Tampilkan perbedaan bila sumber bertentangan</label>
                        <label class="bd-dp__check"><input type="hidden" name="uncertainty" value="0"><input type="checkbox" name="uncertainty" value="1" data-dp-field @checked($g['uncertainty'])> Nyatakan ketidakpastian dan batas bukti</label>
                    </div>
                    <p class="bd-ix__hint">Tiga pilihan ini diteruskan sebagai arahan ke model. Perilaku model nyata tidak dijamin oleh pengaturan ini; sitasi tetap diverifikasi sistem.</p>

                    <h4 class="bd-ix__side-title">Pesan saat jawaban tidak dapat dibuat</h4>
                    <p class="bd-ix__hint">Pesan ini ditampilkan sistem tanpa memanggil AI. Kosongkan untuk memakai teks bawaan. Kondisi "bertentangan" tidak dapat dideteksi otomatis, jadi tidak punya pesan sendiri.</p>
                    @foreach ($conditions as $key => $label)
                        <details class="bd-ix__group" @if (data_get($g, "unavailable.$key")) open @endif>
                            <summary class="bd-ix__group-title">{{ $label }}</summary>
                            <div class="bd-ix__field">
                                <label class="bd-ix__label" for="ab-un-{{ $key }}-m">Pesan</label>
                                <textarea class="bd-ix__input bd-ix__textarea" id="ab-un-{{ $key }}-m" name="unavailable[{{ $key }}][message]" rows="2" maxlength="{{ \App\Services\Research\AnswerBehavior::MAX_COPY }}" placeholder="{{ $systemCopy[$key]['message'] }}" data-dp-field>{{ data_get($g, "unavailable.$key.message") }}</textarea>
                                <p class="bd-ix__note" data-error-for="unavailable.{{ $key }}.message"></p>
                            </div>
                            <div class="bd-ix__field">
                                <label class="bd-ix__label" for="ab-un-{{ $key }}-n">Langkah berikutnya</label>
                                <textarea class="bd-ix__input bd-ix__textarea" id="ab-un-{{ $key }}-n" name="unavailable[{{ $key }}][next_action]" rows="2" maxlength="{{ \App\Services\Research\AnswerBehavior::MAX_COPY }}" placeholder="{{ $systemCopy[$key]['next_action'] }}" data-dp-field>{{ data_get($g, "unavailable.$key.next_action") }}</textarea>
                                <p class="bd-ix__note" data-error-for="unavailable.{{ $key }}.next_action"></p>
                            </div>
                        </details>
                    @endforeach
                @else
                    @php $fu = $config['follow_up']; @endphp
                    <label class="bd-dp__check"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" data-dp-field @checked($fu['enabled'])> Sarankan pertanyaan lanjutan</label>
                    <div class="bd-ix__grid bd-dp__grid3">
                        <div class="bd-ix__field">
                            <label class="bd-ix__label" for="ab-min">Jumlah minimal</label>
                            <input class="bd-ix__input" id="ab-min" name="min" type="number" min="0" max="6" value="{{ $fu['min'] }}" data-dp-field>
                            <p class="bd-ix__note" data-error-for="min"></p>
                        </div>
                        <div class="bd-ix__field">
                            <label class="bd-ix__label" for="ab-max">Jumlah maksimal</label>
                            <input class="bd-ix__input" id="ab-max" name="max" type="number" min="1" max="6" value="{{ $fu['max'] }}" data-dp-field>
                            <p class="bd-ix__note" data-error-for="max"></p>
                        </div>
                        <div class="bd-ix__field">
                            <label class="bd-ix__label" for="ab-detail">Rincian</label>
                            <select class="bd-ix__input bd-ix__select" id="ab-detail" name="detail" data-dp-field>
                                <option value="short" @selected($fu['detail'] === 'short')>Singkat</option>
                                <option value="detailed" @selected($fu['detail'] === 'detailed')>Rinci</option>
                            </select>
                        </div>
                    </div>
                    <p class="bd-ix__hint">Minimal hanya permintaan ke model; maksimal ditegakkan server. Pertanyaan disusun dari topik, alat, analisis, dan bukti, bukan daftar tetap, dan boleh kosong bila tidak relevan.</p>
                    <div class="bd-dp__checks" role="group" aria-label="Jenis pertanyaan">
                        @foreach ($followTypes as $value => $label)
                            <label class="bd-dp__check"><input type="checkbox" name="types[]" value="{{ $value }}" data-dp-field @checked(in_array($value, $fu['types'], true))> {{ $label }}</label>
                        @endforeach
                    </div>
                    <p class="bd-ix__note" data-error-for="types"></p>
                    <div class="bd-ix__field bd-ab__narrow">
                        <label class="bd-ix__label" for="ab-hide">Sembunyikan bila bukti kurang dari</label>
                        <input class="bd-ix__input" id="ab-hide" name="hide_below_evidence" type="number" min="0" max="10" value="{{ $fu['hide_below_evidence'] }}" data-dp-field aria-describedby="ab-hide-h">
                        <p class="bd-ix__note" data-error-for="hide_below_evidence"></p>
                    </div>
                    <p class="bd-ix__hint" id="ab-hide-h">Jumlah potongan bukti yang dipakai jawaban. 0 berarti tidak pernah disembunyikan oleh syarat ini.</p>
                @endif
            </fieldset>

            <div class="bd-ix__field">
                <label class="bd-ix__label" for="ab-text">Isi prompt perilaku <small>wajib</small></label>
                <p class="bd-ix__hint" id="ab-text-h">Arahan tambahan dalam bahasa Anda sendiri. Tidak boleh melemahkan aturan tetap di bawah.</p>
                <textarea class="bd-ix__input bd-ix__textarea is-mono" id="ab-text" name="task_prompt" rows="9" maxlength="{{ $max }}" spellcheck="false" data-dp-field data-dp-text data-max="{{ $max }}" aria-describedby="ab-text-h">{{ $version->task_prompt }}</textarea>
                <div class="bd-ix__under">
                    <p class="bd-ix__note" data-error-for="task_prompt"></p>
                    <span class="bd-ix__count" data-dp-count="task_prompt" aria-hidden="true"></span>
                </div>
            </div>
        </div>
        <p class="bd-ix__alert" data-dp-error role="alert" hidden></p>
        <div class="bd-ix__actions">
            <button type="button" class="bd-ix__btn bd-ix__btn--quiet" data-dp-revert>Batalkan perubahan</button>
            <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-save>Simpan draf</button>
        </div>
    </form>
@else
    <div class="bd-ix__reader">
        <section class="bd-ix__read">
            <h4>Pengaturan</h4>
            @if ($config === [])
                <p class="bd-ix__muted">Tidak terbaca.</p>
            @else
                <dl class="bd-ab__dl">
                    <div><dt>Kelompok</dt><dd>{{ $groups[$group]['label'] }}</dd></div>
                    <div><dt>Cakupan</dt><dd>{{ $scopes[$config['scope']] }}@if ($config['tool_code']) · {{ $tools[$config['tool_code']] ?? $config['tool_code'] }}@endif</dd></div>
                    @if ($s = $config['style'] ?? null)
                        <div><dt>Kedalaman</dt><dd>{{ $depths[$s['depth']] ?? $s['depth'] }}</dd></div>
                        <div><dt>Register</dt><dd>{{ $registers[$s['register']] ?? $s['register'] }}</dd></div>
                        <div><dt>Bahasa</dt><dd>{{ $languages[$s['language']] ?? $s['language'] }}</dd></div>
                        <div><dt>Jelaskan istilah teknis</dt><dd>{{ $s['explain_terms'] ? 'Ya' : 'Tidak' }}</dd></div>
                    @endif
                    @if ($s = $config['structure'] ?? null)
                        <div><dt>Bagian jawaban</dt><dd>{{ collect($s['sections'])->map(fn ($k) => $sectionOptions[$k] ?? $k)->implode(' → ') }}</dd></div>
                    @endif
                    @if ($g = $config['grounding'] ?? null)
                        <div><dt>Dukungan sebagian</dt><dd>{{ $g['partial'] ? 'Ya' : 'Tidak' }}</dd></div>
                        <div><dt>Sumber bertentangan</dt><dd>{{ $g['conflict'] ? 'Ya' : 'Tidak' }}</dd></div>
                        <div><dt>Nyatakan ketidakpastian</dt><dd>{{ $g['uncertainty'] ? 'Ya' : 'Tidak' }}</dd></div>
                        @foreach ($conditions as $key => $label)
                            <div><dt>{{ $label }}</dt><dd>{{ data_get($g, "unavailable.$key.message") ?: 'Teks bawaan sistem' }}@if (data_get($g, "unavailable.$key.next_action"))<br><span class="bd-ix__sub">{{ data_get($g, "unavailable.$key.next_action") }}</span>@endif</dd></div>
                        @endforeach
                    @endif
                    @if ($f = $config['follow_up'] ?? null)
                        <div><dt>Pertanyaan lanjutan</dt><dd>{{ $f['enabled'] ? 'Aktif, '.$f['min'].'–'.$f['max'].' pertanyaan' : 'Nonaktif' }}</dd></div>
                        @if ($f['enabled'])
                            <div><dt>Jenis</dt><dd>{{ collect($f['types'])->map(fn ($k) => $followTypes[$k] ?? $k)->implode(', ') }}</dd></div>
                            <div><dt>Rincian</dt><dd>{{ $f['detail'] === 'detailed' ? 'Rinci' : 'Singkat' }}</dd></div>
                            <div><dt>Sembunyikan bila bukti kurang dari</dt><dd>{{ $f['hide_below_evidence'] }}</dd></div>
                        @endif
                    @endif
                </dl>
            @endif
        </section>
        <section class="bd-ix__read">
            <h4>Isi prompt perilaku</h4>
            <pre class="bd-ix__prompt" tabindex="0">{{ $version->task_prompt }}</pre>
        </section>
    </div>
@endif

<details class="bd-ix__group bd-ab__fixed">
    <summary class="bd-ix__group-title">Aturan tetap sistem <small>tidak dapat diubah dari sini</small></summary>
    <p class="bd-ix__hint">Berlaku untuk setiap jawaban dan selalu berada di atas aturan Anda. Aturan di atas hanya mengatur penyajian.</p>
    <ul class="bd-ab__fixed-list">
        @foreach ($fixedRules as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>
</details>
