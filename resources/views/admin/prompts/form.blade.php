@extends('layouts.admin')
@section('title', ($prompt->exists ? 'Edit Prompt' : 'Create Prompt').' — Admin')
@section('content')
<section class="admin-page narrow-page">
    <div class="admin-hero compact" data-admin-reveal>
        <div><p class="eyebrow">ADMIN / PROMPT LIBRARY</p><h1>{{ $prompt->exists ? 'Edit prompt.' : 'Create a prompt.' }}</h1><p>Write the controlled research instruction that users will select from the platform.</p></div>
        <a href="{{ route('admin.prompts.index') }}" class="btn btn-secondary">Back to prompts</a>
    </div>

    @if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ $prompt->exists ? route('admin.prompts.update', $prompt) : route('admin.prompts.store') }}" class="admin-form-card" data-admin-reveal>
        @csrf
        @if($prompt->exists) @method('PUT') @endif

        <div class="admin-form-section"><span class="admin-kicker">BASIC INFORMATION</span><h2>Prompt identity</h2></div>
        <div class="admin-form-grid">
            <label>Name<input name="name" value="{{ old('name', $prompt->name) }}" placeholder="Literature Review" required></label>
            <label>Research tool<select name="ai_tool_id"><option value="">Unassigned</option>@foreach($tools as $tool)<option value="{{ $tool->id }}" @selected(old('ai_tool_id',$prompt->ai_tool_id)==$tool->id)>{{ $tool->name }}</option>@endforeach</select></label>
            <label>Version<input type="number" min="1" max="9999" name="version" value="{{ old('version', $prompt->version ?: 1) }}" required></label>
            <label>Status<select name="status"><option value="draft" @selected(old('status',$prompt->status)=='draft')>Draft</option><option value="active" @selected(old('status',$prompt->status)=='active')>Active</option><option value="archived" @selected(old('status',$prompt->status)=='archived')>Archived</option></select></label>
        </div>

        <div class="admin-form-section"><span class="admin-kicker">AI INSTRUCTIONS</span><h2>Controlled research behaviour</h2></div>
        <label>System prompt <span class="field-optional">optional</span><textarea name="system_prompt" rows="7" placeholder="Set the assistant's role and boundaries...">{{ old('system_prompt',$prompt->system_prompt) }}</textarea></label>
        <label>Task prompt<textarea name="task_prompt" rows="8" placeholder="Analyse the selected internal sources and identify..." required>{{ old('task_prompt',$prompt->task_prompt) }}</textarea></label>
        <label>Retrieval instructions <span class="field-optional">optional</span><textarea name="retrieval_instructions" rows="6" placeholder="Prioritise sources from the selected category...">{{ old('retrieval_instructions',$prompt->retrieval_instructions) }}</textarea></label>
        <label>Output instructions <span class="field-optional">optional</span><textarea name="output_instructions" rows="6" placeholder="Return a structured answer with source citations...">{{ old('output_instructions',$prompt->output_instructions) }}</textarea></label>

        <div class="admin-form-footer"><button class="btn btn-primary" type="submit">{{ $prompt->exists ? 'Save changes' : 'Create prompt' }}</button><a class="btn btn-secondary" href="{{ route('admin.prompts.index') }}">Cancel</a></div>
    </form>
</section>
@endsection
