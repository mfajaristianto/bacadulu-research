<section class="bd-sc__insights" aria-labelledby="sc-ins-title">
    <div class="bd-sc__ins-head">
        <h2 id="sc-ins-title" class="bd-ix__side-title">Insight Keamanan</h2>
        <p class="bd-sc__sub">Dihitung langsung dari event tercatat. Bersifat deskriptif: tidak menilai ada tidaknya serangan.</p>
    </div>
    <ul>
        @foreach ($insights as $i => $ins)
            <li style="--i: {{ $i }}">
                <span class="bd-sc__ins-label">{{ $ins['label'] }}</span>
                <strong class="bd-sc__ins-value">{{ $ins['value'] }}</strong>
                @if ($ins['pct'] !== null)<span class="bd-sc__progress" aria-hidden="true" style="--w: {{ $ins['pct'] }}%"></span>@endif
                <span class="bd-sc__ins-note">{{ $ins['note'] }}</span>
            </li>
        @endforeach
    </ul>
</section>
