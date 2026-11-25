@php
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
    $wib = fn ($t, $fmt = 'j M Y') => $t ? \Carbon\CarbonImmutable::parse($t, 'UTC')->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat($fmt) : null;
@endphp
@if ($users->total() === 0)
    <div class="bd-ix__empty">
        @if ($filtering)
            <h2>Tidak ada pengguna yang cocok</h2>
            <p>Tidak ada akun yang sesuai dengan pencarian dan filter ini. Coba kata kunci lain atau atur ulang filter.</p>
            <a href="{{ $u(['q' => null, 'peran' => null, 'status' => null, 'verifikasi' => null, 'daftar' => null, 'riset' => null]) }}" class="bd-ix__btn" data-us-nav>Atur ulang filter</a>
        @else
            <h2>Belum ada pengguna terdaftar</h2>
            <p>Akun muncul di sini setelah seseorang mendaftar di BacaDulu Research.</p>
        @endif
    </div>
@else
    <p class="bd-us__count">Menampilkan {{ $nf($users->firstItem()) }}–{{ $nf($users->lastItem()) }} dari {{ $nf($users->total()) }} pengguna.</p>
    <div class="bd-us__tablewrap">
        <table class="bd-us__dtable" aria-label="Daftar pengguna">
            <thead>
                <tr>
                    <th scope="col">Nama</th>
                    <th scope="col">Email</th>
                    <th scope="col">Tanggal daftar</th>
                    <th scope="col">Terakhir aktif</th>
                    <th scope="col" class="is-num">Riset</th>
                    <th scope="col" class="is-num">Dokumen</th>
                    <th scope="col">Device terakhir</th>
                    <th scope="col">IP terakhir</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="bd-us__sr">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    @php
                        $isAdmin = $adminEmail !== '' && strtolower($user->email) === $adminEmail;
                        $ev = $user->last_login_event;
                        $active = $ev?->last_activity_at ?? $ev?->login_at ?? $user->last_login_at;
                        $suspended = $user->status !== 'active';
                    @endphp
                    <tr class="bd-us__item {{ $suspended ? 'is-suspended' : '' }}">
                        <th scope="row" class="bd-us__cell-name">
                            <a class="bd-us__row" href="{{ request()->fullUrlWithQuery(['pengguna' => $user->id]) }}" data-us-detail="{{ route('admin.users.show', $user) }}" data-us-id="{{ $user->id }}" aria-label="Lihat detail {{ $user->name }}">{{ $user->name }}</a>
                            @if ($isAdmin)<em class="bd-us__tag">Administrator</em>@endif
                        </th>
                        <td class="bd-us__cell-mail">{{ $user->email }}</td>
                        <td>{{ $wib($user->created_at) }}</td>
                        <td>{{ $active ? $wib($active, 'j M Y, H.i') : '—' }}</td>
                        <td class="is-num">{{ $nf($user->ai_runs_count) }}</td>
                        <td class="is-num">{{ $nf($user->document_generations_count) }}</td>
                        <td>{{ $ev ? \App\Services\Users\DeviceParser::label($ev->device_type, $ev->os, $ev->browser) : '—' }}</td>
                        <td>{{ $ev?->ip_address ?: '—' }}</td>
                        <td><span class="bd-us__state bd-us__state--{{ $suspended ? 'suspended' : 'active' }}">{{ $suspended ? 'Nonaktif' : 'Aktif' }}</span></td>
                        <td class="bd-us__cell-actions">
                            <details class="bd-us__menu">
                                <summary class="bd-ix__btn bd-ix__btn--small bd-ix__btn--quiet" aria-label="Aksi untuk {{ $user->name }}">Aksi</summary>
                                <ul>
                                    <li><a href="{{ request()->fullUrlWithQuery(['pengguna' => $user->id]) }}" data-us-detail="{{ route('admin.users.show', $user) }}">Lihat detail</a></li>
                                    <li><a href="{{ route('admin.ai-runs.index', ['search' => $user->email]) }}">Lihat riset</a></li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.users.tutorial-reset', $user) }}">@csrf
                                            <button type="submit">Reset Tutorial</button>
                                        </form>
                                    </li>
                                    @unless ($isAdmin)
                                        <li>
                                            <form method="POST" action="{{ route('admin.users.status', $user) }}">@csrf @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $suspended ? 'active' : 'suspended' }}">
                                                <button type="submit">{{ $suspended ? 'Aktifkan pengguna' : 'Nonaktifkan pengguna' }}</button>
                                            </form>
                                        </li>
                                        <li><a href="{{ route('admin.users.research.confirm', $user) }}" class="is-danger">Hapus riset…</a></li>
                                        <li><a href="{{ route('admin.users.delete.confirm', $user) }}" class="is-danger">Hapus pengguna…</a></li>
                                    @endunless
                                </ul>
                            </details>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($users->hasPages())
        <nav class="bd-ix__pager" aria-label="Halaman pengguna">
            @if ($users->onFirstPage())
                <span class="bd-ix__btn bd-ix__btn--quiet" aria-disabled="true">Sebelumnya</span>
            @else
                <a class="bd-ix__btn" href="{{ $users->previousPageUrl() }}" data-us-nav rel="prev">Sebelumnya</a>
            @endif
            <span class="bd-ix__muted">Halaman {{ $users->currentPage() }} dari {{ $users->lastPage() }}</span>
            @if ($users->hasMorePages())
                <a class="bd-ix__btn" href="{{ $users->nextPageUrl() }}" data-us-nav rel="next">Berikutnya</a>
            @else
                <span class="bd-ix__btn bd-ix__btn--quiet" aria-disabled="true">Berikutnya</span>
            @endif
        </nav>
    @endif
@endif
