@extends('layouts.admin')

@php
    $editing = $category->exists;
@endphp

@section('title', $editing ? 'Edit Kategori' : 'Tambah Kategori')
@section('page_title', 'Kategori Riset')

{{-- Halaman form biasa: cadangan tanpa JavaScript dan tujuan tautan "Buka kategori". Daftar kategori memakai modal. --}}
@section('content')
<section class="admin-page bd-cat bd-cat--page">
    <header class="bd-cat__head">
        <div>
            <a href="{{ route('admin.categories.index') }}" class="bd-cat__back">@include('admin.categories.partials.icon', ['name' => 'arrow']) Kategori</a>
            <h1>{{ $editing ? 'Edit Kategori' : 'Tambah Kategori' }}</h1>
            <p>Gunakan nama yang jelas dan stabil agar pengelompokan sumber konsisten.</p>
        </div>
    </header>

    <form method="POST" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="bd-cat__pageform" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="bd-cat__fields">
            @include('admin.categories.partials.fields', ['category' => $category, 'parents' => $parents, 'blocked' => $blocked, 'modal' => false])
        </div>

        <div class="bd-cat__dialog-actions">
            <a href="{{ route('admin.categories.index') }}" class="bd-cat__btn bd-cat__btn--quiet">Batal</a>
            <button type="submit" class="bd-cat__btn bd-cat__btn--primary">{{ $editing ? 'Simpan perubahan' : 'Buat kategori' }}</button>
        </div>
    </form>
</section>
@endsection
