@php
    $stateClass = ['active' => 'active', 'draft' => 'draft', 'archived' => 'archived'][$rule['state']];
    $stateLabel = ['active' => 'Aktif', 'draft' => 'Draf', 'archived' => 'Diarsipkan'][$rule['state']];
@endphp
<header class="bd-dp__head">
    <a href="{{ route('admin.answer-behavior.index') }}" class="bd-dp__back" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'arrow']) Semua aturan</a>
    <div class="bd-dp__head-main">
        <div>
            <h1>{{ $rule['title'] }}</h1>
            <p class="bd-dp__meta">
                <span class="bd-ix__state bd-ix__state--{{ $stateClass }}" data-dp-state="{{ $rule['state'] }}">{{ $stateLabel }}</span>
                <span>{{ $groups[$rule['group']]['label'] ?? 'Konfigurasi rusak' }}</span>
                <span>{{ $scopes[$rule['scope']] ?? '—' }}@if ($rule['tool_name']) · {{ $rule['tool_name'] }}@elseif ($rule['tool_code']) · {{ $rule['tool_code'] }}@endif</span>
                @if ($rule['active'])<span>v{{ $rule['active']->version }} dipakai riset</span>@endif
            </p>
            @if ($rule['description'])
                <p class="bd-ix__muted bd-dp__lead">{{ $rule['description'] }}</p>
            @endif
        </div>
        @if ($rule['active'])
            <div class="bd-dp__head-actions">
                <form method="POST" action="{{ route('admin.answer-behavior.deactivate', $rule['uuid']) }}" data-dp-form data-dp-ask-title="Nonaktifkan aturan ini?" data-dp-ask-text="v{{ $rule['active']->version }} diarsipkan (tetap tersimpan) dan riset berikutnya tidak lagi memakai aturan ini. Hasil riset lama tidak berubah. Aktifkan versi mana pun kapan saja untuk memakainya lagi." data-dp-ask-ok="Nonaktifkan" data-dp-ask-danger>
                    @csrf
                    <button type="submit" class="bd-ix__btn bd-ix__btn--quiet">Nonaktifkan aturan</button>
                </form>
            </div>
        @endif
    </div>

    @if ($rule['scope'] === 'document')
        <p class="bd-ix__lock is-draft">@include('admin.tools.partials.icon', ['name' => 'lock']) Aturan penulisan dokumen tersimpan dan terbaca resolver, tetapi belum dipakai: generator dokumen AI belum terhubung ke Research Workspace.</p>
    @endif
    @if ($rule['tool_issue'])
        <p class="bd-ix__lock is-draft">@include('admin.tools.partials.icon', ['name' => 'lock']) {{ $rule['tool_issue'] }} Aturan khusus alat hanya berlaku ketika alat itu dipakai.</p>
    @endif
</header>
