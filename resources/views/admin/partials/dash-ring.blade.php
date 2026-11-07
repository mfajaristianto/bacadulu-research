{{-- Donut persentase ringan (SVG). $pct 0-100, $tone: gold|green|coral, $label: teks pengganti untuk pembaca layar. --}}
<span class="bd-dash__ring bd-dash__ring--{{ $tone ?? 'gold' }}" role="img" aria-label="{{ $label }}">
    <svg viewBox="0 0 36 36" aria-hidden="true">
        <circle class="bd-dash__ring-track" cx="18" cy="18" r="15.9" pathLength="100" />
        <circle class="bd-dash__ring-fill" cx="18" cy="18" r="15.9" pathLength="100" stroke-dasharray="100" stroke-dashoffset="{{ 100 - $pct }}" data-dash-ring="{{ $pct }}" />
    </svg>
    <span class="bd-dash__ring-value" aria-hidden="true"><b data-dash-count="{{ $pct }}">{{ $pct }}</b>%</span>
</span>
