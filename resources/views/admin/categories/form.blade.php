@extends('layouts.admin')

@php($editing = $category->exists)

@section('title', $editing ? 'Edit Category' : 'Tambah Category')
@section('page_title', 'Categories')

@section('content')
<section class="admin-page">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy">
            <p class="bd-admin-kicker">KNOWLEDGE / CATEGORY</p>
            <h1>{{ $editing ? 'Edit Category' : 'Tambah Category' }}</h1>
            <p>Gunakan nama yang jelas dan stabil agar klasifikasi source konsisten.</p>
        </div>
        <div class="bd-admin-page-head__actions"><a href="{{ route('admin.categories.index') }}" class="bd-admin-button bd-admin-button--ghost">← Categories</a></div>
    </header>

    <form method="POST" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="bd-admin-form-shell">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="bd-admin-form">
            <section class="bd-admin-form__section">
                <div class="bd-admin-form__section-head"><strong>Category Detail</strong><p>Slug dibuat otomatis dari nama category.</p></div>
                <div class="bd-admin-form__grid">
                    <label class="bd-admin-form-field is-full"><span>Nama</span><input class="bd-admin-input" name="name" value="{{ old('name', $category->name) }}" maxlength="180" required></label>
                    <label class="bd-admin-form-field"><span>Parent <small>opsional</small></span><select class="bd-admin-select" name="parent_id"><option value="">Tidak ada</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected((string) old('parent_id', $category->parent_id) === (string) $parent->id)>{{ $parent->name }}</option>@endforeach</select></label>
                    <label class="bd-admin-form-field"><span>Status</span><select class="bd-admin-select" name="status"><option value="active" @selected(old('status', $category->status ?: 'active') === 'active')>Active</option><option value="archived" @selected(old('status', $category->status) === 'archived')>Archived</option></select></label>
                </div>
            </section>
            <section class="bd-admin-form__section"><button class="bd-admin-button bd-admin-button--primary" type="submit">{{ $editing ? 'Simpan Perubahan' : 'Buat Category' }}</button></section>
        </div>

        <aside class="bd-admin-form__aside"><section><strong>Prinsip</strong><p>Category adalah klasifikasi utama source. Collection digunakan untuk bundel lintas category.</p></section></aside>
    </form>
</section>
@endsection
