@extends('layouts.admin')
@section('title','Admin Overview — BacaDulu Research')
@section('content')
<section class="admin-page admin-overview-page">
    <div class="admin-hero" data-admin-reveal>
        <div>
            <p class="eyebrow">ADMIN / OVERVIEW</p>
            <h1>Knowledge, prompts, and control.</h1>
            <p>Manage the trusted internal knowledge base that powers BacaDulu Research.</p>
        </div>
        <div class="admin-hero-actions">
            <a href="{{ route('admin.library.create') }}" class="btn btn-primary">Add source <span>+</span></a>
            <a href="{{ route('admin.prompts.create') }}" class="btn btn-secondary">Create prompt</a>
        </div>
    </div>

    <div class="admin-stat-grid" data-admin-reveal>
        <article class="admin-stat-card"><span>Internal sources</span><strong>{{ $documents }}</strong><small>records in the knowledge base</small></article>
        <article class="admin-stat-card admin-stat-accent"><span>Published</span><strong>{{ $publishedDocuments }}</strong><small>public sources eligible for user retrieval</small></article>
        <article class="admin-stat-card"><span>Prompt templates</span><strong>{{ $prompts }}</strong><small>admin-managed research instructions</small></article>
        <article class="admin-stat-card"><span>Research tools</span><strong>{{ $tools }}</strong><small>configured AI workflows</small></article>
    </div>

    <div class="admin-content-grid" data-admin-reveal>
        <section class="admin-panel-card admin-panel-large">
            <div class="admin-panel-head">
                <div><span class="admin-kicker">KNOWLEDGE BASE</span><h2>Internal Library</h2></div>
                <a href="{{ route('admin.library.index') }}">Manage all ↗</a>
            </div>
            <p class="admin-panel-description">Only sources marked <strong>published</strong> and <strong>public</strong> are exposed to the normal-user retrieval layer. Categories and visibility can be managed per source.</p>
            <div class="admin-quick-grid">
                <a href="{{ route('admin.library.index') }}"><span class="quick-icon">◫</span><strong>Library</strong><small>Upload, edit and publish sources.</small></a>
                <a href="{{ route('admin.prompts.index') }}"><span class="quick-icon">✦</span><strong>Prompt Library</strong><small>Create the research tasks users can select.</small></a>
            </div>
        </section>

        <aside class="admin-panel-card">
            <div class="admin-panel-head"><div><span class="admin-kicker">PLATFORM</span><h2>Accounts</h2></div></div>
            <div class="admin-big-number">{{ $users }}</div>
            <p>registered research accounts</p>
            <div class="admin-rule"></div>
            <div class="admin-mini-row"><span>AI provider</span><strong>{{ config('research.ai_provider', 'unconfigured') }}</strong></div>
            <div class="admin-mini-row"><span>Model</span><strong>{{ config('research.ai_model') ?: 'Not configured' }}</strong></div>
        </aside>
    </div>
</section>
@endsection
