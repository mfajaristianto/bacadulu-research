@extends('layouts.admin')

@php
    $editing = $tool->exists;
@endphp

@section('title', $editing ? 'Edit Alat Riset' : 'Tambah Alat Riset')
@section('page_title', 'Katalog Alat AI')

{{-- Halaman form biasa: cadangan tanpa JavaScript dan tujuan tautan "Buka alat". Daftar alat memakai dialog. --}}
@section('content')
<section class="admin-page bd-tl bd-tl--page">
    <header class="bd-tl__head">
        <div>
            <a href="{{ route('admin.tools.index') }}" class="bd-tl__back">@include('admin.tools.partials.icon', ['name' => 'arrow']) Katalog Alat AI</a>
            <h1>{{ $editing ? $tool->name : 'Tambah Alat Riset' }}</h1>
            <p>Alat menjelaskan jenis pekerjaan riset. Prompt-nya ada di Instruksi AI, riwayatnya di Versi Instruksi.</p>
        </div>
    </header>

    <form method="POST" action="{{ $editing ? route('admin.tools.update', $tool) : route('admin.tools.store') }}" class="bd-tl__pageform" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="bd-tl__fields">
            @include('admin.tools.partials.fields', ['tool' => $tool, 'modal' => false])
        </div>

        @if ($editing)
            @php $prompt = $tool->activePromptTemplate; @endphp
            <div class="bd-tl__linked">
                <span class="bd-tl__label">Instruksi AI aktif</span>
                <p>
                    @if ($prompt) {{ $prompt->name }} · v{{ $prompt->version }} · {{ $prompt->status }}
                    @else Belum ada instruksi aktif; alat ini belum dapat dipakai pengguna.
                    @endif
                </p>
                <a class="bd-tl__link" href="{{ route('admin.prompt-versions.index', ['tool' => $tool->id]) }}">Kelola instruksi dan versi</a>
            </div>
        @endif

        <div class="bd-tl__dialog-actions">
            <a href="{{ route('admin.tools.index') }}" class="bd-tl__btn bd-tl__btn--quiet">Batal</a>
            <button type="submit" class="bd-tl__btn bd-tl__btn--primary">{{ $editing ? 'Simpan perubahan' : 'Buat alat' }}</button>
        </div>
    </form>
</section>
@endsection
