@extends('layouts.admin')

@php
    $editing = $collection->exists;
@endphp

@section('title', $editing ? 'Edit Koleksi' : 'Tambah Koleksi')
@section('page_title', 'Koleksi')

{{-- Halaman form biasa: cadangan tanpa JavaScript dan tujuan tautan "Buka koleksi". Daftar koleksi memakai dialog. --}}
@section('content')
<section class="admin-page bd-col bd-col--page">
    <header class="bd-col__head">
        <div>
            <a href="{{ route('admin.collections.index') }}" class="bd-col__back">@include('admin.collections.partials.icon', ['name' => 'arrow']) Koleksi</a>
            <h1>{{ $editing ? 'Edit Koleksi' : 'Tambah Koleksi' }}</h1>
            <p>Pilih sumber yang ingin dikumpulkan dalam konteks riset yang sama.</p>
        </div>
    </header>

    <form method="POST" action="{{ $editing ? route('admin.collections.update', $collection) : route('admin.collections.store') }}" class="bd-col__pageform" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="sync_documents" value="1">

        <div class="bd-col__fields">
            <div class="bd-col__field">
                <label class="bd-col__label" for="col-name">Nama koleksi</label>
                <input class="bd-col__input" id="col-name" name="name" type="text" maxlength="180" required value="{{ old('name', $collection->name) }}" placeholder="Mis. ESG dan Nilai Perusahaan" @if ($errors->has('name')) aria-invalid="true" @endif>
                <p class="bd-col__note">{{ $errors->first('name') }}</p>
            </div>
            <div class="bd-col__field">
                <label class="bd-col__label" for="col-description">Deskripsi <small>opsional</small></label>
                <textarea class="bd-col__input bd-col__textarea" id="col-description" name="description" rows="3" maxlength="2000">{{ old('description', $collection->description) }}</textarea>
                <p class="bd-col__note">{{ $errors->first('description') }}</p>
            </div>
            <div class="bd-col__field">
                <label class="bd-col__label" for="col-status">Status</label>
                <select class="bd-col__select" id="col-status" name="status" required>
                    <option value="active" @selected(old('status', $collection->status ?: 'active') === 'active')>Aktif</option>
                    <option value="archived" @selected(old('status', $collection->status) === 'archived')>Diarsipkan</option>
                </select>
                <p class="bd-col__note">{{ $errors->first('status') }}</p>
            </div>

            <div class="bd-col__field">
                <span class="bd-col__label">Sumber dalam koleksi</span>
                <p class="bd-col__hint">Koleksi tidak mengubah kategori sumber. Mencentang hanya menautkan sumber; PDF tidak digandakan.</p>
                <div class="bd-col__checklist">
                    @forelse ($documents as $document)
                        <label class="bd-col__cand">
                            <span class="bd-col__check"><input type="checkbox" name="document_ids[]" value="{{ $document->id }}" @checked(in_array($document->id, old('document_ids', $selectedDocuments), true))><i></i></span>
                            <span class="bd-col__cand-text"><strong>{{ $document->title }}</strong><small>{{ $document->category?->name ?: 'Tanpa kategori' }} · {{ $document->status }}</small></span>
                        </label>
                    @empty
                        <p class="bd-col__muted">Belum ada sumber di Pustaka.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bd-col__dialog-actions">
            <a href="{{ route('admin.collections.index') }}" class="bd-col__btn bd-col__btn--quiet">Batal</a>
            <button type="submit" class="bd-col__btn bd-col__btn--primary">{{ $editing ? 'Simpan perubahan' : 'Buat koleksi' }}</button>
        </div>
    </form>
</section>
@endsection
