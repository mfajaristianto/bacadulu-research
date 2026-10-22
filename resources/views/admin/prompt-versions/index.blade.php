@extends('layouts.admin')

@section('title', 'Prompt Versions')
@section('page_title', 'Prompt Versions')

@section('content')
<section class="admin-page">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy">
            <p class="bd-admin-kicker">AI CONTROL / PROMPT VERSIONS</p>
            <h1>Prompt Versions</h1>
            <p>Riwayat instruction yang pernah dibuat untuk setiap Research Tool. Versi aktif tidak diedit langsung.</p>
        </div>
    </header>

    <form method="GET" class="bd-admin-filter">
        <input class="bd-admin-input" type="search" name="search" value="{{ request('search') }}" placeholder="Cari prompt…">
        <select class="bd-admin-select" name="tool" onchange="this.form.submit()">
            <option value="">Semua Research Tool</option>
            @foreach($tools as $tool)
                <option value="{{ $tool->id }}" @selected((string) request('tool') === (string) $tool->id)>{{ $tool->name }}</option>
            @endforeach
        </select>
        <select class="bd-admin-select" name="status" onchange="this.form.submit()">
            <option value="">Semua status</option>
            @foreach(['draft','active','archived'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </form>

    <div class="bd-admin-table-wrap">
        <table class="bd-admin-table">
            <thead><tr><th>Prompt</th><th>Research Tool</th><th>Version</th><th>Status</th><th>Updated</th><th></th></tr></thead>
            <tbody>
            @forelse($versions as $prompt)
                <tr>
                    <td><div class="bd-admin-table__title"><strong>{{ $prompt->display_name }}</strong><small>PROMPT-{{ str_pad($prompt->id, 4, '0', STR_PAD_LEFT) }}</small></div></td>
                    <td>{{ $prompt->aiTool?->name ?: 'Unassigned' }}</td>
                    <td>v{{ $prompt->version }}</td>
                    <td><span class="bd-admin-status bd-admin-status--{{ $prompt->status }}">{{ ucfirst($prompt->status) }}</span></td>
                    <td>{{ optional($prompt->updated_at)->format('d M Y · H:i') }}</td>
                    <td>
                        <div class="bd-admin-inline-actions">
                            <a class="bd-admin-table__action" href="{{ route('admin.prompts.edit', $prompt) }}">Open</a>
                            <form method="POST" action="{{ route('admin.prompt-versions.duplicate', $prompt) }}">@csrf<button class="bd-admin-link-button" type="submit">New version</button></form>
                            @if($prompt->status !== 'active')
                                <form method="POST" action="{{ route('admin.prompt-versions.activate', $prompt) }}">@csrf @method('PATCH')<button class="bd-admin-link-button" type="submit">Activate</button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="bd-admin-empty"><div class="bd-admin-empty__inner"><span class="bd-admin-empty__mark">VER</span><h3>Belum ada prompt version.</h3><p>Buat prompt dari Prompt Manager untuk memulai version history.</p></div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $versions->links() }}
</section>
@endsection
