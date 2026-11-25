@php
    $wibShort = fn ($date) => $date?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i');
    $vlabel = fn ($v) => ['active' => 'Sedang Aktif', 'draft' => 'Draf', 'archived' => 'Diarsipkan'][$v->status] ?? $v->status;
    $sel = $selected;
    $toolArchived = $tool?->status === 'archived';
    $ready = $active && $tool && ! $toolArchived && (int) $tool->active_prompt_template_id === $active->id;
    $detailUrl = fn ($v) => route('admin.prompts.index', ['instruksi' => $sel->id, 'v' => $v->id]);
    $other = $versions->first(fn ($v) => $v->id !== $sel->id);
    $defaultA = ($active && $active->id !== $sel->id ? $active : $other)?->id;
@endphp

<div class="bd-ix__page" data-ix-detail data-selected="{{ $sel->id }}">
    <a href="{{ route('admin.prompts.index') }}" class="bd-ix__back" data-ix-nav>@include('admin.tools.partials.icon', ['name' => 'arrow']) Semua instruksi</a>

    <header class="bd-ix__detail-head">
        <h2 class="bd-ix__page-title">{{ $name }}</h2>
        @if (filled($active?->indonesian_name ?? $sel->indonesian_name))
            <p class="bd-ix__muted">{{ $active?->indonesian_name ?? $sel->indonesian_name }}</p>
        @endif
        <p class="bd-ix__tie">
            @if ($tool)
                Terhubung ke <a class="bd-ix__link" href="{{ route('admin.tools.index', ['search' => $tool->code]) }}">Katalog Alat AI / {{ $tool->name }}</a>
                ·
                @if ($ready)
                    <span class="bd-ix__ok">Siap dipakai pengguna</span>
                @elseif ($toolArchived)
                    <span class="bd-ix__muted">alat diarsipkan, belum tersedia untuk pengguna</span>
                @elseif ($active)
                    <span class="bd-ix__warn">penunjuk alat belum sinkron dengan versi aktif</span>
                @else
                    <span class="bd-ix__warn">belum ada versi aktif, alat belum siap dipakai</span>
                @endif
            @else
                <span class="bd-ix__warn">Belum terhubung ke alat riset, jadi belum bisa diaktifkan.</span>
            @endif
        </p>
    </header>

    <div class="bd-ix__cols">
        <aside class="bd-ix__versions" aria-labelledby="ix-vh">
            <h3 id="ix-vh" class="bd-ix__side-title">Riwayat versi</h3>
            <ol class="bd-ix__vlist">
                @foreach ($versions as $v)
                    <li>
                        <a class="bd-ix__vrow {{ $v->id === $sel->id ? 'is-selected' : '' }}" href="{{ $detailUrl($v) }}" data-ix-nav @if ($v->id === $sel->id && ! $compare) aria-current="true" @endif>
                            <span class="bd-ix__vnum">v{{ $v->version }}</span>
                            <span class="bd-ix__vstate bd-ix__vstate--{{ $v->status }}">{{ $vlabel($v) }}</span>
                            <span class="bd-ix__vmeta">{{ $wibShort($v->updated_at) }}@if ($v->ai_runs_count) · dipakai {{ $v->ai_runs_count }} riset @endif</span>
                        </a>
                    </li>
                @endforeach
            </ol>

            @if ($versions->count() > 1)
                <form method="GET" action="{{ route('admin.prompts.index') }}" class="bd-ix__compare" data-ix-compare>
                    <input type="hidden" name="instruksi" value="{{ $sel->id }}">
                    <input type="hidden" name="v" value="{{ $sel->id }}">
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
                                <option value="{{ $v->id }}" @selected($v->id === ($compare['new']->id ?? $sel->id))>v{{ $v->version }} · {{ $vlabel($v) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="bd-ix__btn bd-ix__btn--small">Bandingkan</button>
                </form>
            @endif
        </aside>

        <div class="bd-ix__main" data-ix-main>
            @if ($compare)
                @include('admin.prompts.partials.compare')
            @else
                @php $editable = ! $lock; @endphp
                <header class="bd-ix__vhead">
                    <div>
                        <h3 class="bd-ix__vtitle">v{{ $sel->version }} <span class="bd-ix__vstate bd-ix__vstate--{{ $sel->status }}">{{ $vlabel($sel) }}</span></h3>
                        <p class="bd-ix__muted">Diperbarui {{ $wibShort($sel->updated_at) }}@if ($sel->creator) · dibuat oleh {{ $sel->creator->name }}@endif · {{ $sel->ai_runs_count ? 'dipakai '.$sel->ai_runs_count.' riset' : 'belum dipakai riset' }}</p>
                    </div>
                    <div class="bd-ix__vactions">
                        @if ($sel->status === 'active')
                            <span class="bd-ix__active-tag">@include('admin.tools.partials.icon', ['name' => 'check']) Sedang Aktif</span>
                        @elseif ($tool)
                            <form method="POST" action="{{ route('admin.prompts.activate', $sel) }}" data-ix-act="activate" data-version="v{{ $sel->version }}" data-current="{{ $active ? 'v'.$active->version : '' }}" data-tool="{{ $tool->name }}" data-tool-archived="{{ $toolArchived ? 1 : 0 }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-ix-activate>Jadikan Aktif</button>
                            </form>
                        @endif
                        @if ($sel->status !== 'draft')
                            <form method="POST" action="{{ route('admin.prompts.revise', $sel) }}" data-ix-act="revise" data-version="v{{ $sel->version }}">
                                @csrf
                                <button type="submit" class="bd-ix__btn">Buat revisi dari v{{ $sel->version }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.prompts.archive', $sel) }}" data-ix-act="archive" data-version="v{{ $sel->version }}">
                                @csrf
                                @method('PATCH')
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

                @if ($editable)
                    <form method="POST" action="{{ route('admin.prompts.update', $sel) }}" class="bd-ix__form" novalidate data-ix-form data-ix-kind="update">
                        @csrf
                        @method('PUT')
                        @include('admin.prompts.partials.fields', ['prompt' => $sel, 'readonly' => false])
                        <p class="bd-ix__alert" data-ix-error role="alert" @unless ($errors->any()) hidden @endunless>{{ $errors->any() ? 'Periksa isian yang ditandai.' : '' }}</p>
                        <div class="bd-ix__actions">
                            <button type="button" class="bd-ix__btn bd-ix__btn--quiet" data-ix-revert>Batalkan perubahan</button>
                            <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-ix-save>Simpan draf</button>
                        </div>
                    </form>
                @else
                    <div class="bd-ix__reader">
                        @include('admin.prompts.partials.fields', ['prompt' => $sel, 'readonly' => true])
                    </div>
                @endif
            @endif
        </div>
    </div>

    <div class="bd-ix__below">
        <section aria-labelledby="ix-rel">
            <h3 id="ix-rel" class="bd-ix__side-title">Hubungan dengan alat</h3>
            @if ($tool)
                <dl class="bd-ix__facts">
                    <div><dt>Alat</dt><dd>{{ $tool->name }} <code>{{ $tool->code }}</code></dd></div>
                    <div><dt>Status alat</dt><dd>{{ $toolArchived ? 'Diarsipkan' : 'Aktif' }}</dd></div>
                    <div><dt>Versi yang dibaca alat</dt><dd>{{ $active ? $active->name.' v'.$active->version : 'Belum ada' }}</dd></div>
                    <div><dt>Untuk pengguna</dt><dd>{{ $ready ? 'Tersedia' : 'Belum tersedia' }}</dd></div>
                </dl>
                <p class="bd-ix__muted">Mengaktifkan versi tidak mengubah status alat. Riset baru memakai versi aktif; riset lama tetap tercatat dengan versi yang dulu dipakai.</p>
            @else
                <p class="bd-ix__muted">Instruksi ini belum terhubung ke alat riset.</p>
            @endif
        </section>

        <section aria-labelledby="ix-audit">
            <h3 id="ix-audit" class="bd-ix__side-title">Riwayat perubahan</h3>
            @php
                $actionLabels = ['prompt.created' => 'Instruksi dibuat', 'prompt.updated' => 'Draf diubah', 'prompt.duplicated' => 'Revisi dibuat', 'prompt.activated' => 'Dijadikan aktif', 'prompt.archived' => 'Draf diarsipkan'];
            @endphp
            @if ($history->isEmpty())
                <p class="bd-ix__muted">Belum ada catatan perubahan.</p>
            @else
                <ol class="bd-ix__log">
                    @foreach ($history as $log)
                        <li>
                            <span>{{ $actionLabels[$log->action] ?? $log->action }} <strong>v{{ $versionById[$log->auditable_id]->version ?? '?' }}</strong>@if (! empty($log->metadata_json['previous_version'])), menggantikan v{{ $log->metadata_json['previous_version'] }}@endif</span>
                            <small>{{ $log->user?->name ?? 'Sistem' }} · {{ $wibShort($log->created_at) }}</small>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</div>
