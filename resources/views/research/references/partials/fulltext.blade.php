@php($s = ($r['kind'] ?? null) === 'external' ? ($ft[$r['id']] ?? null) : null)
@if($s && ! $s['checked'])
    <p class="refs-ft__note refs-ft__note--solo">Teks lengkap belum diperiksa: saat ini hanya metadata dan abstrak yang tersedia untuk sumber ini.</p>
@elseif($s)
    <div class="refs-ft" role="group" aria-label="Status teks lengkap">
        <p class="refs-ft__title">Teks lengkap</p>
        <ul class="refs-ft__steps">
            <li class="is-on">Sumber ditemukan</li>
            <li class="{{ $s['open_text'] ? 'is-on' : '' }}">{{ $s['open_text'] ? 'Memiliki teks terbuka' : 'Tidak ada tautan teks terbuka' }}</li>
            <li class="{{ $s['stored'] ? 'is-on' : '' }}">{{ $s['stored'] ? 'Disimpan secara sah' : 'Belum disimpan' }}</li>
            <li class="{{ $s['indexed'] ? 'is-on' : '' }}">{{ $s['indexed'] ? 'Terindeks, siap jadi evidence' : 'Belum diindeks' }}</li>
        </ul>
        <p class="refs-ft__note">
            @if($s['indexed'])
                Isi artikel sudah diindeks{{ $s['chunks'] ? ' ('.$s['chunks'].' potongan teks)' : '' }}{{ $s['license'] ? ' dengan lisensi '.strtoupper($s['license']) : '' }}.
            @elseif($s['state'] === 'LICENSE_REVIEW_REQUIRED')
                Lisensi belum jelas; admin perlu meninjau sebelum teks lengkap boleh disimpan. Tautan terbuka bukan izin menyimpan.
            @elseif($s['state'] === 'EXTRACTION_FAILED' || $s['state'] === 'DOWNLOAD_FAILED')
                Pemrosesan teks lengkap belum berhasil; saat ini hanya abstrak dan metadata yang dipakai.
            @else
                Ketersediaan teks lengkap tidak mengubah peringkat relevansi.
            @endif
        </p>
    </div>
@endif
