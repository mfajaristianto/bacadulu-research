@extends('layouts.admin')

@php
    $editing = $term->exists;
@endphp

@section('title', $editing ? 'Edit Istilah' : 'Tambah Istilah')
@section('page_title', 'Kamus Istilah Pencarian')

{{-- Halaman form biasa: cadangan tanpa JavaScript dan tujuan tautan "Buka istilah". Daftar glosarium memakai dialog. --}}
@section('content')
<section class="admin-page bd-gl bd-gl--page">
    <header class="bd-gl__head">
        <div>
            <a href="{{ route('admin.glossary.index') }}" class="bd-gl__back">@include('admin.glossary.partials.icon', ['name' => 'arrow']) Glosarium</a>
            <h1>{{ $editing ? 'Edit Istilah' : 'Tambah Istilah' }}</h1>
            <p>Glosarium membantu AI memahami istilah. Ia bukan sumber evidence dan tidak dapat disitasi.</p>
        </div>
    </header>

    <form method="POST" action="{{ $editing ? route('admin.glossary.update', $term) : route('admin.glossary.store') }}" class="bd-gl__pageform" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="bd-gl__fields">
            @include('admin.glossary.partials.fields', ['term' => $term, 'categories' => $categories, 'modal' => false])
        </div>

        <div class="bd-gl__dialog-actions">
            <a href="{{ route('admin.glossary.index') }}" class="bd-gl__btn bd-gl__btn--quiet">Batal</a>
            <button type="submit" class="bd-gl__btn bd-gl__btn--primary">{{ $editing ? 'Simpan perubahan' : 'Buat istilah' }}</button>
        </div>
    </form>
</section>
@endsection
