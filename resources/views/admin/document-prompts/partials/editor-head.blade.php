@php
    $enabledCount = $sections->where('is_enabled', true)->count();
    $issueCount = count($readiness['issues']);
    $stateClass = in_array($state['key'], ['ready', 'unsupported'], true) ? 'active' : ($state['key'] === 'archived' ? 'archived' : 'draft');
    $hasDraftToActivate = $sections->contains(fn ($s) => $s->is_enabled && ($sectionInfo[$s->id]['active'] === null) && $sectionInfo[$s->id]['draft']);
    $bagian = $selected?->key;
@endphp
<header class="bd-dp__head">
    <a href="{{ route('admin.prompt-documents.index') }}" class="bd-dp__back" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'arrow']) Semua template</a>
    <div class="bd-dp__head-main">
        <div>
            <h1>{{ $template->name }}</h1>
            <p class="bd-dp__meta">
                <span class="bd-ix__state bd-ix__state--{{ $stateClass }}" data-dp-state="{{ $state['key'] }}">{{ $state['label'] }}</span>
                <span>{{ $template->document_type === 'other' && $template->type_label ? $template->type_label : $types[$template->document_type] }}</span>
                <span>{{ $languages[$template->language] ?? $template->language }}</span>
                <span>{{ $template->citation_style }}</span>
                <span>Q&amp;A: {{ strtolower($qaModes[$template->qa_mode]) }}</span>
            </p>
            @if ($template->description)
                <p class="bd-ix__muted bd-dp__lead">{{ $template->description }}</p>
            @endif
        </div>
        <div class="bd-dp__head-actions">
            <button type="button" class="bd-ix__btn" data-dp-open="preview">@include('admin.tools.partials.icon', ['name' => 'eye']) Pratinjau Struktur</button>
            <button type="button" class="bd-ix__btn" data-dp-open="settings">Pengaturan</button>
            <form method="POST" action="{{ route('admin.prompt-documents.duplicate', $template->id) }}" data-dp-form data-dp-ask-title="Salin template ini?" data-dp-ask-text="Salinan dibuat sebagai draf baru. Instruksinya berupa draf v1 yang belum aktif, dan tidak berbagi versi dengan template ini." data-dp-ask-ok="Salin template">
                @csrf
                <button type="submit" class="bd-ix__btn">Salin</button>
            </form>
            @if ($template->status === 'archived')
                <form method="POST" action="{{ route('admin.prompt-documents.status', $template->id) }}" data-dp-form data-dp-ask-title="Pulihkan template?" data-dp-ask-text="Template kembali sebagai draf dan belum dapat dipilih sampai diaktifkan lagi." data-dp-ask-ok="Pulihkan">
                    @csrf <input type="hidden" name="to" value="draft"><input type="hidden" name="bagian" value="{{ $bagian }}">
                    <button type="submit" class="bd-ix__btn">Pulihkan sebagai draf</button>
                </form>
            @else
                @if ($template->status !== 'active')
                    <form method="POST" action="{{ route('admin.prompt-documents.status', $template->id) }}" data-dp-form data-dp-ask-title="Aktifkan template ini?" data-dp-ask-text="Template menjadi aktif dan dapat dipakai Research Workspace bila generator terhubung. Semua syarat sudah diperiksa server." data-dp-ask-ok="Aktifkan template">
                        @csrf <input type="hidden" name="to" value="active"><input type="hidden" name="bagian" value="{{ $bagian }}">
                        <button type="submit" class="bd-ix__btn bd-ix__btn--primary" @disabled($issueCount > 0) @if ($issueCount > 0) title="Lengkapi {{ $issueCount }} hal terlebih dahulu" @endif data-dp-template-activate>Aktifkan template</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.prompt-documents.status', $template->id) }}" data-dp-form data-dp-ask-title="Arsipkan template ini?" data-dp-ask-text="Template tidak dapat dipilih untuk pekerjaan baru. Semua bagian dan versi tetap tersimpan dan bisa dipulihkan." data-dp-ask-ok="Arsipkan" data-dp-ask-danger>
                    @csrf <input type="hidden" name="to" value="archived"><input type="hidden" name="bagian" value="{{ $bagian }}">
                    <button type="submit" class="bd-ix__btn bd-ix__btn--quiet">Arsipkan</button>
                </form>
            @endif
        </div>
    </div>

    @if ($issueCount > 0)
        <details class="bd-dp__issues" @if ($state['key'] === 'incomplete') open @endif>
            <summary><span class="bd-ix__warn">{{ $issueCount }} hal perlu dilengkapi</span> <span class="bd-ix__sub">{{ $template->status === 'active' ? 'agar template kembali siap digunakan' : 'sebelum template bisa diaktifkan' }}</span></summary>
            <ul>
                @foreach ($readiness['issues'] as $issue)
                    <li>
                        @if ($issue['section'])
                            <a class="bd-ix__link" href="{{ route('admin.prompt-documents.show', ['template' => $template->id, 'bagian' => $issue['section']]) }}" data-dp-nav>{{ $issue['message'] }}</a>
                        @else
                            {{ $issue['message'] }}
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($hasDraftToActivate)
                <form method="POST" action="{{ route('admin.prompt-documents.activate-drafts', $template->id) }}" data-dp-form data-dp-ask-title="Aktifkan draf yang lengkap?" data-dp-ask-text="Untuk setiap bagian yang belum punya versi aktif, draf terbarunya diaktifkan bila valid. Bagian yang sudah punya versi aktif tidak diubah. Template itu sendiri tidak ikut diaktifkan." data-dp-ask-ok="Aktifkan draf">
                    @csrf <input type="hidden" name="bagian" value="{{ $bagian }}">
                    <button type="submit" class="bd-ix__btn bd-ix__btn--small">Aktifkan draf yang lengkap</button>
                </form>
            @endif
        </details>
    @else
        <p class="bd-dp__ok">@include('admin.tools.partials.icon', ['name' => 'check']) Semua syarat konfigurasi terpenuhi ({{ $enabledCount }} bagian aktif).@if (! $readiness['contract']) <span class="bd-ix__muted">Jenis ini belum punya kontrak generator, jadi tidak diberi label "Siap".</span>@endif</p>
    @endif
</header>
