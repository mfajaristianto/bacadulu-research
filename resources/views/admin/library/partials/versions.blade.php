{{-- Riwayat versi satu sumber. Butuh $document dan $history (SourceVersions::history). Dipakai baris daftar dan halaman detail. --}}
@php
    $size = fn (int $bytes): string => $bytes >= 1048576
        ? number_format($bytes / 1048576, 1, ',', '.').' MB'
        : number_format(max($bytes, 1) / 1024, 0, ',', '.').' KB';
@endphp
<ol class="bd-lib__timeline" aria-label="Riwayat versi {{ $document->title }}">
    @foreach ($history as $version)
        <li class="bd-lib__ver is-{{ $version['state'] }} {{ $version['is_active'] ? 'is-active' : '' }}">
            <span class="bd-lib__node" aria-hidden="true"></span>

            <div>
                <p class="bd-lib__ver-title">
                    <strong>{{ $version['label'] }}</strong>
                    <span class="bd-lib__tag bd-lib__tag--{{ $version['state'] }}">{{ $version['state_label'] }}</span>
                    @if ($version['restored_from'])
                        <span class="bd-lib__muted">dipulihkan dari v{{ $version['restored_from'] }}</span>
                    @endif
                </p>
                <p class="bd-lib__ver-meta">
                    {{ $version['name'] }}
                    @if ($version['size']) · {{ $size($version['size']) }}@endif
                    · <time datetime="{{ $version['uploaded_at']?->toIso8601String() }}">{{ $version['uploaded_at']?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i') }} WIB</time>
                    @if ($version['uploader']) · oleh {{ $version['uploader'] }}@endif
                    @if ($version['hash']) · <span title="SHA-256 diawali {{ $version['hash'] }}">{{ $version['hash'] }}</span>@endif
                </p>
            </div>

            <div class="bd-lib__ver-actions">
                @if ($version['missing'])
                    <span class="bd-lib__note">File tidak ditemukan di penyimpanan</span>
                @else
                    <button type="button" class="bd-lib__btn bd-lib__btn--quiet bd-lib__btn--small" data-lib-view data-url="{{ route('admin.library.files.view', $version['id']) }}" data-download="{{ route('admin.library.files.download', $version['id']) }}" data-title="{{ $document->title }}" data-version="{{ $version['label'] }} · {{ $version['state_label'] }}">Lihat</button>
                    <a class="bd-lib__btn bd-lib__btn--quiet bd-lib__btn--small" href="{{ route('admin.library.files.download', $version['id']) }}">Unduh</a>
                    @if ($version['can_restore'])
                        <form method="POST" action="{{ route('admin.library.files.restore', [$document, $version['id']]) }}" data-lib-confirm data-confirm-title="Pulihkan {{ $version['label'] }}?" data-confirm-text="Isi {{ $version['label'] }} akan disalin menjadi versi baru dan diproses ulang. Semua versi yang ada tetap tersimpan. Sumber perlu dipublikasikan lagi setelah pemrosesan selesai." data-confirm-ok="Pulihkan versi">
                            @csrf
                            <button type="submit" class="bd-lib__btn bd-lib__btn--quiet bd-lib__btn--small">Pulihkan</button>
                        </form>
                    @endif
                @endif
            </div>
        </li>
    @endforeach
</ol>
