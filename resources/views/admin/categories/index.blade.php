@extends('layouts.admin')

@section('title', 'Categories')
@section('page_title', 'Categories')

@section('content')
<section class="admin-page">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy">
            <p class="bd-admin-kicker">KNOWLEDGE / CATEGORIES</p>
            <h1>Categories</h1>
            <p>Struktur utama untuk mengelompokkan source sebelum masuk ke retrieval.</p>
        </div>
        <div class="bd-admin-page-head__actions">
            <a href="{{ route('admin.categories.create') }}" class="bd-admin-button bd-admin-button--primary">Tambah Category ＋</a>
        </div>
    </header>

    <form method="GET" class="bd-admin-filter">
        <input class="bd-admin-input" type="search" name="search" value="{{ request('search') }}" placeholder="Cari kategori…">
        <select class="bd-admin-select" name="status" data-auto-submit>
            <option value="">Semua status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="archived" @selected(request('status') === 'archived')>Archived</option>
        </select>
        <span></span>
    </form>

    <div class="bd-admin-table-wrap">
        <table class="bd-admin-table">
            <thead><tr><th>Category</th><th>Parent</th><th>Sources</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($categories as $category)
                <tr>
                    <td><div class="bd-admin-table__title"><strong>{{ $category->name }}</strong><small>{{ $category->slug }}</small></div></td>
                    <td>{{ $category->parent?->name ?: '—' }}</td>
                    <td>{{ $category->documents_count }}</td>
                    <td><span class="bd-admin-status bd-admin-status--{{ $category->status }}">{{ ucfirst($category->status) }}</span></td>
                    <td><a class="bd-admin-table__action" href="{{ route('admin.categories.edit', $category) }}">Edit →</a></td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="bd-admin-empty"><div class="bd-admin-empty__inner"><span class="bd-admin-empty__mark">CAT</span><h3>Belum ada category.</h3><p>Buat category pertama agar source dapat diklasifikasikan.</p></div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $categories->links() }}
</section>
@endsection
