@php
    $labels = ['terkonfigurasi' => 'Terkonfigurasi', 'teruji' => 'Teruji', 'tersedia' => 'Tersedia', 'belum-diverifikasi' => 'Belum dapat diverifikasi', 'tidak-didukung' => 'Tidak didukung', 'bermasalah' => 'Bermasalah'];
@endphp
<div class="bd-st__sys">
    <section class="bd-st__sysblock" aria-labelledby="st-sys-int">
        <h3 class="bd-st__h" id="st-sys-int">Status integrasi</h3>
        <p class="bd-st__sub">Diperiksa dari konfigurasi dan data lokal. Halaman ini tidak menghubungi layanan pihak ketiga dan tidak menguji koneksi otomatis.</p>
        <ul class="bd-st__integ">
            @foreach ($integrations as $i)
                <li>
                    <div class="bd-st__integ-top">
                        <strong>{{ $i['name'] }}</strong>
                        <span class="bd-st__st bd-st__st--{{ $i['status'] }}">{{ $labels[$i['status']] }}</span>
                    </div>
                    <p class="bd-st__integ-text">{{ $i['text'] }}</p>
                    <p class="bd-st__meta"><span>Dasar: {{ $i['basis'] }}</span>@if ($i['note'])<span>{{ $i['note'] }}</span>@endif</p>
                    @if ($i['link'])<a class="bd-st__link" href="{{ $i['link'] }}">Kelola @include('admin.tools.partials.icon', ['name' => 'arrow'])</a>@endif
                </li>
            @endforeach
        </ul>
    </section>

    <section class="bd-st__sysblock" aria-labelledby="st-sys-foreign">
        <h3 class="bd-st__h" id="st-sys-foreign">Dikelola di Provider &amp; Analitik AI</h3>
        <p class="bd-st__sub">Nilai ini memakai penyimpanan yang sama dengan modul Provider. Agar tidak ada dua sumber kebenaran, perubahannya hanya dilakukan di sana.</p>
        <div class="bd-st__roList">
            @foreach ($foreign as $f)
                <div class="bd-st__ro">
                    <div><strong>{{ $f['label'] }}</strong></div>
                    <span class="bd-st__rovalue">{{ $f['value'] }} {{ $f['unit'] }}</span>
                </div>
            @endforeach
        </div>
        <a class="bd-st__link" href="{{ route('admin.providers.index') }}">Kelola di Provider &amp; Analitik AI @include('admin.tools.partials.icon', ['name' => 'arrow'])</a>
        <p class="bd-ix__hint">Kunci API tidak pernah ditampilkan. Status kunci: {{ $apiKeyConfigured ? 'terdeteksi di server' : 'tidak terdeteksi' }}.</p>
    </section>

    <section class="bd-st__sysblock" aria-labelledby="st-sys-server">
        <h3 class="bd-st__h" id="st-sys-server">Konfigurasi server (hanya baca)</h3>
        <p class="bd-st__sub">Berasal dari environment server dan tidak dapat diubah dari aplikasi. Perubahan dilakukan oleh pengelola server.</p>
        <dl class="bd-st__dl">
            @foreach ($server as $s)
                <div><dt>{{ $s['label'] }}</dt><dd>{{ $s['value'] }}@if ($s['note'])<small>{{ $s['note'] }}</small>@endif</dd></div>
            @endforeach
        </dl>
    </section>

    <section class="bd-st__sysblock" aria-labelledby="st-sys-other">
        <h3 class="bd-st__h" id="st-sys-other">Dikelola di modul lain</h3>
        <ul class="bd-st__links">
            <li><a href="{{ route('admin.answer-behavior.index') }}">Perilaku Jawaban AI @include('admin.tools.partials.icon', ['name' => 'arrow'])</a><span>gaya jawaban, sitasi, saran lanjutan</span></li>
            <li><a href="{{ route('admin.prompt-documents.index') }}">Prompt Dokumen @include('admin.tools.partials.icon', ['name' => 'arrow'])</a><span>instruksi per bagian dokumen</span></li>
            <li><a href="{{ route('admin.document-templates.index') }}">Template &amp; Format Cetak @include('admin.tools.partials.icon', ['name' => 'arrow'])</a><span>margin, kertas, huruf, tata letak ekspor</span></li>
            <li><a href="{{ route('admin.library.index') }}">Pustaka @include('admin.tools.partials.icon', ['name' => 'arrow'])</a><span>dokumen dan koleksi sumber</span></li>
            <li><a href="{{ route('admin.security.index') }}">Keamanan @include('admin.tools.partials.icon', ['name' => 'arrow'])</a><span>akses, autentikasi, kontrol perlindungan</span></li>
            <li><a href="{{ route('admin.users.index') }}">Pengguna @include('admin.tools.partials.icon', ['name' => 'arrow'])</a><span>akun, status, dan aktivitas pengguna</span></li>
        </ul>
    </section>
</div>
