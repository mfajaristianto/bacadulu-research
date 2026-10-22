@extends('layouts.admin')

@section('title', 'Research Tools')
@section('page_title', 'Research Tools')

@section('content')
<section class="admin-page">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy">
            <p class="bd-admin-kicker">AI CONTROL / RESEARCH TOOLS</p>
            <h1>Research Tools</h1>
            <p>Identitas pekerjaan research yang tersedia untuk user. Instruction internal dikelola melalui Prompt Versions.</p>
        </div>
        <div class="bd-admin-page-head__actions">
            <a href="{{ route('admin.tools.create') }}" class="bd-admin-button bd-admin-button--primary">Tambah Tool ＋</a>
        </div>
    </header>

    <form method="GET" action="{{ route('admin.tools.index') }}" class="bd-admin-filter">
        <input class="bd-admin-input" type="search" name="search" value="{{ request('search') }}" placeholder="Cari tool atau code…">
        <select class="bd-admin-select" name="status" onchange="this.form.submit()"><option value="">Semua status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="archived" @selected(request('status') === 'archived')>Archived</option></select>
        <span></span>
    </form>

    <div class="bd-admin-table-wrap">
        <table class="bd-admin-table">
            <thead><tr><th>Tool</th><th>Code</th><th>Active Prompt</th><th>Versions</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($tools as $tool)
                <tr>
                    <td><div class="bd-admin-table__title"><strong>{{ $tool->name }}</strong><small>{{ Str::limit($tool->description ?: 'Tanpa deskripsi', 100) }}</small></div></td>
                    <td><code>{{ $tool->code }}</code></td>
                    <td>@if($tool->activePromptTemplate)<strong>{{ $tool->activePromptTemplate->name }}</strong><small> v{{ $tool->activePromptTemplate->version }}</small>@else<span class="bd-admin-status">Belum ada</span>@endif</td>
                    <td>{{ $tool->prompt_templates_count }}</td>
                    <td><span class="bd-admin-status bd-admin-status--{{ $tool->status }}">{{ ucfirst($tool->status) }}</span></td>
                    <td><a class="bd-admin-table__action" href="{{ route('admin.tools.edit', $tool) }}">Edit →</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="bd-admin-empty"><div class="bd-admin-empty__inner"><span class="bd-admin-empty__mark">TOOL</span><h3>Belum ada Research Tool.</h3><p>Tool canonical dapat dibuat dari sini jika seeding belum dijalankan.</p></div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $tools->links() }}
</section>
@endsection
