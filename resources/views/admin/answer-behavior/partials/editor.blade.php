@php
    $tabs = ['aturan' => 'Aturan', 'versi' => 'Riwayat versi', 'pratinjau' => 'Pratinjau'];
    $tabUrl = fn ($t) => route('admin.answer-behavior.show', array_filter(['rule' => $rule['uuid'], 'tab' => $t !== 'aturan' ? $t : null, 'v' => $version->id]));
@endphp
<div class="bd-dp__pane bd-ab__pane" id="dp-pane" tabindex="-1" data-dp-editor>
    <div class="bd-dp__tabs" role="tablist" aria-label="Pengaturan aturan">
        @foreach ($tabs as $key => $label)
            <a href="{{ $tabUrl($key) }}" role="tab" class="bd-dp__tab {{ $tab === $key ? 'is-on' : '' }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" data-dp-nav>{{ $label }}</a>
        @endforeach
    </div>
    @include('admin.answer-behavior.partials.pane-'.$tab)
</div>
