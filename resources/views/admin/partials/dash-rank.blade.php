{{-- Daftar peringkat dengan batang horizontal. $items: [['name','sub','count']], $unit: satuan, $empty: teks kosong. --}}
@php $max = max(1, (int) collect($items)->max('count')); @endphp
@if (empty($items))
    <p class="bd-dash__rank-empty">{{ $empty }}</p>
@else
    <ol class="bd-dash__rank">
        @foreach ($items as $item)
            <li style="--w: {{ round($item['count'] / $max * 100) }}%">
                <span class="bd-dash__rank-name">{{ $item['name'] }}@if (! empty($item['sub'])) <small>{{ $item['sub'] }}</small>@endif</span>
                <b class="bd-dash__rank-count">{{ number_format($item['count'], 0, ',', '.') }} <small>{{ $unit }}</small></b>
                <span class="bd-dash__rank-bar" aria-hidden="true"><i></i></span>
            </li>
        @endforeach
    </ol>
@endif
