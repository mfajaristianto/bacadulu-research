{{-- Sinyal kualitas data (informasi untuk admin, bukan filter). $q = QualitySignals::evaluate(...) ; $only = 'warn' membatasi ke peringatan. --}}
@php
    $chipClass = ['ok' => 'is-ok', 'warn' => 'is-warn', 'info' => 'is-mute'];
    $items = collect([$q['match']])->filter()->concat($q['flags']);
    if (($only ?? null) === 'warn') {
        $items = $items->filter(fn ($i) => $i['level'] !== 'info' || str_starts_with($i['key'], 'abstract'));
    }
@endphp
@if ($items->isNotEmpty())
    <span class="bd-ext__quality" aria-label="Sinyal kualitas data">
        @foreach ($items as $i)
            <span class="bd-ext__chip {{ $chipClass[$i['level']] ?? 'is-mute' }}" data-quality="{{ $i['key'] }}">{{ $i['label'] }}</span>
        @endforeach
    </span>
@endif
