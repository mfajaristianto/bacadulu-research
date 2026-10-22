@extends('layouts.admin')

@php($editing = $collection->exists)

@section('title', $editing ? 'Edit Collection' : 'Tambah Collection')
@section('page_title', 'Collections')

@section('content')
<section class="admin-page">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy"><p class="bd-admin-kicker">KNOWLEDGE / COLLECTION</p><h1>{{ $editing ? 'Edit Collection' : 'Tambah Collection' }}</h1><p>Pilih source yang ingin dikumpulkan dalam konteks yang sama.</p></div>
        <div class="bd-admin-page-head__actions"><a href="{{ route('admin.collections.index') }}" class="bd-admin-button bd-admin-button--ghost">← Collections</a></div>
    </header>

    <form method="POST" action="{{ $editing ? route('admin.collections.update', $collection) : route('admin.collections.store') }}" class="bd-admin-form-shell">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="bd-admin-form">
            <section class="bd-admin-form__section">
                <div class="bd-admin-form__grid">
                    <label class="bd-admin-form-field is-full"><span>Nama Collection</span><input class="bd-admin-input" name="name" value="{{ old('name', $collection->name) }}" maxlength="180" required></label>
                    <label class="bd-admin-form-field is-full"><span>Deskripsi <small>opsional</small></span><textarea class="bd-admin-textarea" name="description" maxlength="2000">{{ old('description', $collection->description) }}</textarea></label>
                    <label class="bd-admin-form-field"><span>Status</span><select class="bd-admin-select" name="status"><option value="active" @selected(old('status', $collection->status ?: 'active') === 'active')>Active</option><option value="archived" @selected(old('status', $collection->status) === 'archived')>Archived</option></select></label>
                </div>
            </section>

            <section class="bd-admin-form__section">
                <div class="bd-admin-form__section-head"><strong>Sources</strong><p>Collection tidak mengubah category utama source.</p></div>
                <div class="bd-admin-checklist">
                    @forelse($documents as $document)
                        <label class="bd-admin-checkrow">
                            <input type="checkbox" name="document_ids[]" value="{{ $document->id }}" @checked(in_array($document->id, old('document_ids', $selectedDocuments), true))>
                            <span><strong>{{ $document->title }}</strong><small>{{ $document->category?->name ?: 'Tanpa kategori' }} · {{ ucfirst($document->status) }}</small></span>
                        </label>
                    @empty
                        <p class="bd-admin-muted">Belum ada source di Library.</p>
                    @endforelse
                </div>
            </section>

            <section class="bd-admin-form__section"><button class="bd-admin-button bd-admin-button--primary" type="submit">{{ $editing ? 'Simpan Collection' : 'Buat Collection' }}</button></section>
        </div>

        <aside class="bd-admin-form__aside"><section><strong>Collection</strong><p>Gunakan untuk grouping tematik, proyek, atau corpus tertentu. Jangan gunakan sebagai pengganti Category.</p></section></aside>
    </form>
</section>
@endsection
