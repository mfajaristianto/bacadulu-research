@php $nf = fn ($n) => number_format((int) $n, 0, ',', '.'); @endphp
<dl class="bd-ix__stats bd-au__stats" aria-label="Ringkasan aktivitas pada periode terpilih">
    <div class="bd-ix__stat"><dt>Total aktivitas</dt><dd>{{ $nf($overview['total']) }}</dd><dd class="bd-ix__stat-note">pada periode{{ $filtering ? ' dan filter aktif' : '' }}</dd></div>
    <div class="bd-ix__stat"><dt>Aktivitas hari ini</dt><dd>{{ $nf($overview['today']) }}</dd><dd class="bd-ix__stat-note">sejak pukul 00.00 WIB</dd></div>
    <div class="bd-ix__stat"><dt>Perubahan konfigurasi</dt><dd>{{ $nf($overview['config']) }}</dd><dd class="bd-ix__stat-note">AI, template cetak, pengaturan</dd></div>
    <div class="bd-ix__stat"><dt>Aktivitas admin</dt><dd>{{ $nf($overview['admin']) }}</dd><dd class="bd-ix__stat-note">oleh admin yang dikenali</dd></div>
    <div class="bd-ix__stat {{ $overview['failed'] > 0 ? 'is-warn' : '' }}"><dt>Event gagal</dt><dd>{{ $nf($overview['failed']) }}</dd><dd class="bd-ix__stat-note">berdasarkan kode event</dd></div>
    @if ($overview['system'] > 0)
        <div class="bd-ix__stat"><dt>Proses sistem</dt><dd>{{ $nf($overview['system']) }}</dd><dd class="bd-ix__stat-note">riset dan pemrosesan sumber</dd></div>
    @endif
</dl>
