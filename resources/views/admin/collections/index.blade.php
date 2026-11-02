@extends('layouts.admin')

@section('title', 'Collections')
@section('page_title', 'Collections')

@section('content')
<section class="admin-page">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy">
            <p class="bd-admin-kicker">KNOWLEDGE / COLLECTIONS</p>
            <h1>Collections</h1>
            <p>Bundel source lintas category untuk konteks research tertentu.</p>
        </div>
        <div class="bd-admin-page-head__actions"><a href="{{ route('admin.collections.create') }}" class="bd-admin-button bd-admin-button--primary">Tambah Collection ＋</a></div>
    </header>

    <form method="GET" class="bd-admin-filter">
        <input class="bd-admin-input" type="search" name="search" value="{{ request('search') }}" placeholder="Cari collection…">
        <select class="bd-admin-select" name="status" data-auto-submit><option value="">Semua status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="archived" @selected(request('status') === 'archived')>Archived</option></select>
        <span></span>
    </form>

    <div class="bd-admin-table-wrap">
        <table class="bd-admin-table">
            <thead><tr><th>Collection</th><th>Sources</th><th>Status</th><th>Updated</th><th></th></tr></thead>
            <tbody>
            @forelse($collections as $collection)
                <tr>
                    <td><div class="bd-admin-table__title"><strong>{{ $collection->name }}</strong><small>{{ Str::limit($collection->description ?: 'Tanpa deskripsi', 110) }}</small></div></td>
                    <td>{{ $collection->documents_count }}</td>
                    <td><span class="bd-admin-status bd-admin-status--{{ $collection->status }}">{{ ucfirst($collection->status) }}</span></td>
                    <td>{{ optional($collection->updated_at)->format('d M Y') }}</td>
                    <td><a class="bd-admin-table__action" href="{{ route('admin.collections.edit', $collection) }}">Edit →</a></td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="bd-admin-empty"><div class="bd-admin-empty__inner"><span class="bd-admin-empty__mark">COL</span><h3>Belum ada collection.</h3><p>Buat bundel source untuk tema research tertentu.</p></div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $collections->links() }}
</section>
@endsection
