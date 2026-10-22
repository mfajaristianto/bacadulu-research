{{-- Research Tool Picker: selection stays connected to existing prompt runtime --}}
<div class="research-prompt-menu" id="research-prompt-menu" data-prompt-menu role="listbox" aria-label="Pilih Research Tool" hidden>
    <header class="research-prompt-menu__head">
        <div>
            <span>RESEARCH TOOLS</span>
            <strong>Pilih cara analisis</strong>
            <p>Temukan pendekatan yang sesuai untuk pertanyaan research Anda.</p>
        </div>
        <button type="button" data-prompt-close aria-label="Tutup pilihan prompt">×</button>
    </header>
    <div class="research-prompt-menu__body">
        @forelse($prompts as $prompt)
            <div class="research-prompt-option-row">
                <button type="button" class="research-prompt-option" role="option"
                    aria-selected="{{ (int) $activePrompt?->id === (int) $prompt->id ? 'true' : 'false' }}"
                    data-prompt-id="{{ $prompt->id }}"
                    data-prompt-name="{{ $prompt->display_name }}"
                    data-prompt-tool="{{ $prompt->aiTool?->name ?: 'Research Tool' }}"
                    data-prompt-description="{{ $prompt->short_description ?: $prompt->aiTool?->description ?: 'Research prompt' }}">
                    <span class="research-prompt-option__index">{{ str_pad((string)$loop->iteration,2,'0',STR_PAD_LEFT) }}</span>
                    <span class="research-prompt-option__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            @switch($loop->iteration % 4)
                                @case(0)<path d="M4 18h16M7 15V9M12 15V5M17 15v-4"/>@break
                                @case(1)<circle cx="10" cy="10" r="5"/><path d="m14 14 6 6M8 10h4M10 8v4"/>@break
                                @case(2)<path d="M4 6h16M4 12h11M4 18h8"/><circle cx="18" cy="15" r="3"/>@break
                                @default<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8M12 8v8"/>
                            @endswitch
                        </svg>
                    </span>
                    <span class="research-prompt-option__copy">
                        <strong>{{ $prompt->name }}</strong>
                        @if($prompt->indonesian_name)<small class="research-prompt-option__translation">{{ $prompt->indonesian_name }}</small>@endif
                        <p>{{ $prompt->short_description ?: $prompt->aiTool?->description ?: 'Penjelasan prompt belum tersedia.' }}</p>
                    </span>
                    <span class="research-prompt-option__arrow" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </span>
                </button>
                <button type="button" class="research-prompt-info-button" popovertarget="prompt-info-{{ $prompt->id }}" popovertargetaction="show"
                    aria-label="Apa ini: {{ $prompt->name }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/></svg>
                    Apa ini?
                </button>
            </div>
        @empty
            <div class="research-prompt-menu__empty">Belum ada Research Tool yang aktif.</div>
        @endforelse
    </div>
    <footer class="research-prompt-menu__motion-note">Pilih tool untuk menyesuaikan pendekatan analisis.</footer>
</div>
