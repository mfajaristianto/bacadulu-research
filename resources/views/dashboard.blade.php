@extends('layouts.app')

@section('title', 'Research Dashboard — BacaDulu Research')

@section('content')

<section class="dashboard-page">

    <div class="dashboard-head">
        <div>
            <p class="eyebrow">RESEARCH DASHBOARD</p>

            <h1>
                Good to see you,
                {{ Str::of(auth()->user()->name)->explode(' ')->first() }}.
            </h1>

            <p>
                Mulai eksplorasi penelitian dari sumber internal yang terpercaya.
            </p>
        </div>

        <a href="{{ route('workspace') }}" class="btn btn-primary">
            Open Workspace ↗
        </a>
    </div>


    <div class="stat-grid">

        <div class="stat-card">
            <span>Library</span>
            <strong>{{ $libraryCount }}</strong>
            <small>published sources</small>
        </div>

        <div class="stat-card">
            <span>Profile</span>
            <strong>{{ auth()->user()->profileCompletion() }}%</strong>
            <small>profile completion</small>
        </div>

        <div class="stat-card">
            <span>Saved Results</span>
            <strong>{{ $savedResults->count() }}</strong>
            <small>saved research results</small>
        </div>

    </div>


    <div class="dashboard-section">

        <div class="section-mini-head">
            <div>
                <p class="eyebrow">RESEARCH TOOLS</p>
                <h2>Start your research</h2>
            </div>

            <a href="{{ route('workspace') }}">
                Open workspace →
            </a>
        </div>


        <div class="tools-grid dashboard-tools">

            @forelse($tools as $tool)

                <a href="{{ route('workspace') }}" class="tool-card">

                    <span class="tool-icon">
                        {{ $tool->icon ?: '◎' }}
                    </span>

                    <h3>
                        {{ $tool->name }}
                    </h3>

                    <p>
                        {{ $tool->description ?: 'Research analysis tool.' }}
                    </p>

                </a>

            @empty

                <div class="empty-state">
                    <strong>Research tools belum tersedia.</strong>

                    <p>
                        Administrator belum menambahkan research tool.
                    </p>
                </div>

            @endforelse

        </div>

    </div>


    <div class="dashboard-section">

        <div class="section-mini-head">

            <div>
                <p class="eyebrow">INTERNAL LIBRARY</p>
                <h2>Explore trusted sources</h2>
            </div>

            <a href="{{ route('library.index') }}">
                View library →
            </a>

        </div>

        <div class="dashboard-library-card">

            <div>
                <span class="library-number">
                    {{ $libraryCount }}
                </span>

                <span class="library-label">
                    published sources available
                </span>
            </div>

            <a href="{{ route('library.index') }}" class="btn btn-secondary">
                Explore Library
            </a>

        </div>

    </div>

</section>

@endsection