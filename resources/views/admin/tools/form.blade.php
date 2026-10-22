@extends('layouts.admin')

@php($editing = $tool->exists)

@section('title', $editing ? 'Edit Research Tool' : 'Tambah Research Tool')
@section('page_title', 'Research Tools')

@section('content')
<section class="admin-page">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy"><p class="bd-admin-kicker">AI CONTROL / TOOL</p><h1>{{ $editing ? $tool->name : 'Tambah Research Tool' }}</h1><p>Tool menjelaskan jenis pekerjaan research; prompt version mengontrol instruction internal.</p></div>
        <div class="bd-admin-page-head__actions"><a href="{{ route('admin.tools.index') }}" class="bd-admin-button bd-admin-button--ghost">← Research Tools</a></div>
    </header>

    <form method="POST" action="{{ $editing ? route('admin.tools.update', $tool) : route('admin.tools.store') }}" class="bd-admin-form-shell">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="bd-admin-form">
            <section class="bd-admin-form__section">
                <div class="bd-admin-form__section-head"><strong>Tool Identity</strong><p>Code dipakai internal, nama dan deskripsi dapat ditampilkan ke user.</p></div>
                <div class="bd-admin-form__grid">
                    <label class="bd-admin-form-field"><span>Code</span><input class="bd-admin-input" name="code" value="{{ old('code', $tool->code) }}" placeholder="research-gap" required></label>
                    <label class="bd-admin-form-field"><span>Status</span><select class="bd-admin-select" name="status"><option value="active" @selected(old('status', $tool->status ?: 'active') === 'active')>Active</option><option value="archived" @selected(old('status', $tool->status) === 'archived')>Archived</option></select></label>
                    <label class="bd-admin-form-field is-full"><span>Nama</span><input class="bd-admin-input" name="name" value="{{ old('name', $tool->name) }}" maxlength="180" required></label>
                    <label class="bd-admin-form-field is-full"><span>Deskripsi</span><textarea class="bd-admin-textarea" name="description" maxlength="2000">{{ old('description', $tool->description) }}</textarea></label>
                    <input type="hidden" name="icon" value="{{ old('icon', $tool->icon) }}">
                </div>
            </section>
            <section class="bd-admin-form__section"><button class="bd-admin-button bd-admin-button--primary" type="submit">{{ $editing ? 'Simpan Tool' : 'Buat Tool' }}</button></section>
        </div>

        <aside class="bd-admin-form__aside">
            <section><strong>Active Prompt</strong><p>@if($tool->activePromptTemplate){{ $tool->activePromptTemplate->name }} · v{{ $tool->activePromptTemplate->version }}@elseBelum ada Prompt Version aktif.@endif</p></section>
            @if($editing)<section><a href="{{ route('admin.prompt-versions.index', ['tool' => $tool->id]) }}" class="bd-admin-button bd-admin-button--ghost">Lihat Prompt Versions</a></section>@endif
        </aside>
    </form>
</section>
@endsection
