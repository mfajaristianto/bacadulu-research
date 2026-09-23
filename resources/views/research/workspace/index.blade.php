@extends('layouts.app')
@section('title', 'Research Workspace — BacaDulu Research')
@section('content')
<div class="research-chat-shell">
    <aside class="research-chat-sidebar" data-chat-sidebar data-reveal data-reveal-from="left" aria-label="Conversation history">
        <div class="chat-sidebar-top">
            <div><span class="chat-kicker">BacaDulu Research</span><h2>Research workspace</h2></div>
            <button type="button" class="sidebar-close" data-chat-sidebar-close aria-label="Close conversation history">×</button>
        </div>
        <a href="{{ route('workspace') }}" class="chat-new-link"><span>＋</span> New conversation</a>
        <div class="chat-sidebar-label">Recent conversations</div>
        <div class="conversation-list">
            @forelse($conversations as $item)
                <a href="{{ route('workspace.conversation', $item) }}" class="conversation-item {{ $conversation?->id === $item->id ? 'is-active' : '' }}">
                    <strong>{{ Str::limit($item->title, 42) }}</strong>
                    <span>{{ $item->category?->name ?: 'Research' }} · {{ $item->updated_at->diffForHumans() }}</span>
                </a>
            @empty
                <div class="chat-empty-history">Your recent conversations will appear here.</div>
            @endforelse
        </div>
        <div class="chat-sidebar-foot">
            <div><strong>{{ $libraryCount }}</strong><span>eligible internal sources</span></div>
            <a href="{{ route('library.index') }}">Browse library →</a>
        </div>
    </aside>
    <button class="chat-sidebar-backdrop" type="button" data-chat-sidebar-backdrop aria-label="Close conversation history"></button>

    <section class="research-chat-main">
        <header class="chat-topbar" data-reveal data-reveal-from="up">
            <button type="button" class="history-toggle" data-chat-sidebar-open aria-label="Open conversation history">☰</button>
            <div class="chat-title-group">
                <span class="chat-kicker">INTERNAL KNOWLEDGE ASSISTANT</span>
                <h1>{{ $conversation?->title ?: 'What are you researching today?' }}</h1>
            </div>
            <div class="chat-top-meta"><span class="secure-dot"></span> Eligible internal sources only</div>
        </header>

        @if($selectedSource)
            <div class="workspace-source-context" data-reveal data-reveal-from="right">
                <div><span class="chat-kicker">SOURCE IN CONTEXT</span><strong>{{ $selectedSource->title }}</strong><small>{{ $selectedSource->category?->name ?: 'Internal source' }}</small></div>
                <a href="{{ route('library.show', $selectedSource) }}">Open source ↗</a>
            </div>
        @endif

        <div class="chat-messages" data-chat-scroll aria-live="polite">
            @if(!$conversation)
                <div class="chat-welcome" data-reveal data-reveal-from="down">
                    <div class="welcome-mark" aria-hidden="true">BR</div>
                    <p class="chat-kicker">EVIDENCE-GROUNDED WORKSPACE</p>
                    <h2>Ask a research question.</h2>
                    <p>Select a category and one prepared research prompt. The request is grounded in eligible sources from the internal library.</p>
                    <div class="welcome-suggestions"><span>Compare studies</span><span>Find research gaps</span><span>Synthesise findings</span></div>
                </div>
            @else
                @foreach($conversation->messages as $message)
                    <article class="chat-message {{ $message->role === 'user' ? 'is-user' : 'is-assistant' }}" data-reveal data-reveal-from="{{ $message->role === 'user' ? 'right' : 'left' }}">
                        <div class="message-avatar">
                            @if($message->role === 'user')
                                <span class="message-avatar-fallback">{{ auth()->user()->initials }}</span>
                                @if(auth()->user()->avatar_url)
                                    <img src="{{ auth()->user()->avatar_url }}" alt="" data-avatar-image>
                                @endif
                            @else
                                <span>BR</span>
                            @endif
                        </div>
                        <div class="message-body">
                            <div class="message-meta">{{ $message->role === 'user' ? 'You' : 'BacaDulu Research' }}</div>
                            <div class="message-content">{!! nl2br(e($message->content)) !!}</div>
                            @if($message->role === 'assistant' && !empty(data_get($message->metadata, 'status')))
                                <span class="message-status">Queued · waiting for AI provider</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            @endif
        </div>

        <div class="chat-composer-wrap" data-reveal data-reveal-from="up" data-reveal-delay="100">
            @if($errors->any())<div class="alert alert-error chat-error" role="alert">{{ $errors->first() }}</div>@endif
            @if(session('success'))<div class="chat-inline-success" role="status">{{ session('success') }}</div>@endif

            <form method="POST" action="{{ route('research.run') }}" class="chat-composer" data-chat-form>
                @csrf
                @if($conversation)<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">@endif
                @if($selectedSource)<input type="hidden" name="source_id" value="{{ $selectedSource->id }}">@endif

                <div class="composer-tools">
                    <label class="composer-select-wrap">
                        <span class="composer-label">Category</span>
                        <select name="category_id" required aria-label="Research category">
                            <option value="">Choose category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id', $selectedSource?->category_id ?: $conversation?->category_id) == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="prompt-picker-wrap">
                        <span class="composer-label">Prepared prompt</span>
                        <button type="button" class="prompt-picker" data-prompt-open aria-expanded="false" aria-controls="research-prompt-menu"><span class="plus-circle">+</span><span data-prompt-label>Choose a research prompt</span><span class="chevron">⌄</span></button>
                        <input type="hidden" name="prompt_template_id" value="{{ old('prompt_template_id') }}" data-prompt-input required>
                        <div class="prompt-menu" id="research-prompt-menu" data-prompt-menu role="listbox" aria-label="Research prompts" hidden>
                            <div class="prompt-menu-head"><span>Research prompts</span><button type="button" data-prompt-close aria-label="Close prompt menu">×</button></div>
                            @forelse($prompts as $prompt)
                                <button type="button" class="prompt-option" role="option" aria-selected="false" data-prompt-id="{{ $prompt->id }}" data-prompt-name="{{ $prompt->name }}">
                                    <span class="prompt-plus">+</span><span><strong>{{ $prompt->name }}</strong><small>{{ $prompt->aiTool?->name ?: 'Research tool' }}</small></span>
                                </button>
                            @empty
                                <div class="chat-empty-history">No active prompts are available yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="composer-input-row">
                    <textarea name="message" rows="1" placeholder="Ask a research question…" required maxlength="5000" data-chat-input>{{ old('message') }}</textarea>
                    <button class="send-research-btn" type="submit" aria-label="Run research" data-motion-button>↗</button>
                </div>
                <div class="composer-footnote">The selected prompt controls the research task. The retrieval layer uses only eligible internal sources.</div>
            </form>
        </div>
    </section>
</div>
@endsection
