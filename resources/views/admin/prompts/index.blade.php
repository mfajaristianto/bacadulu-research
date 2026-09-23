@extends('layouts.admin')
@section('title', 'Prompt Library — Admin')
@section('content')
<section class="admin-page">
    <div class="admin-hero compact" data-reveal>
        <div><p class="eyebrow">ADMIN / AI CONFIGURATION</p><h1>Prompt Library.</h1><p>Define the controlled research tasks users can select in the workspace.</p></div>
        <a href="{{ route('admin.prompts.create') }}" class="btn btn-primary">Create prompt <span>+</span></a>
    </div>

    <div class="admin-info-banner" data-reveal><span class="quick-icon">P</span><div><strong>One active prompt per assigned research tool</strong><p>Activating a new version automatically moves the previous active prompt for that tool back to draft.</p></div></div>

    <form method="GET" action="{{ route('admin.prompts.index') }}" class="admin-filter" data-reveal>
        <label class="filter-field"><span>Search</span><input type="search" name="search" maxlength="160" value="{{ request('search') }}" placeholder="Prompt name or task…"></label>
        <label class="filter-field"><span>Status</span><select name="status"><option value="">All status</option>@foreach(['draft','active','archived'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
        <div></div>
        <button type="submit" class="btn btn-secondary">Filter</button>
    </form>

    <div class="admin-table-wrap" data-reveal>
        <table class="admin-table admin-table-modern">
            <thead><tr><th>Prompt</th><th>Research tool</th><th>Version</th><th>Status</th><th>Updated</th><th></th></tr></thead>
            <tbody>
            @forelse($prompts as $prompt)
                <tr>
                    <td><strong>{{ $prompt->name }}</strong><small>{{ Str::limit($prompt->task_prompt, 100) }}</small></td>
                    <td>{{ $prompt->aiTool?->name ?: 'Unassigned' }}</td>
                    <td>v{{ $prompt->version }}</td>
                    <td><span class="status-pill status-{{ $prompt->status }}">{{ ucfirst($prompt->status) }}</span></td>
                    <td>{{ optional($prompt->updated_at)->format('d M Y') }}</td>
                    <td><a class="table-link" href="{{ route('admin.prompts.edit', $prompt) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="admin-empty">No prompt templates found.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $prompts->links() }}
</section>
@endsection
