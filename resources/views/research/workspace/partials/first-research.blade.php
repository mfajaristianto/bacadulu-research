{{--
    First Research Experience: halaman New Research untuk user yang belum punya research.
    Memakai form, field, dan hook JS yang sama dengan composer existing
    (data-chat-form, data-chat-input, data-prompt-*). "Coba Contoh" murni UI di sisi client.
--}}
<div
    class="research-new-page is-first-run"
    data-first-research
    data-stage="idle"
    data-demo-title="Pengaruh Literasi Keuangan terhadap Keputusan Investasi Mahasiswa"
>

    <header class="fr-head">
        <h1>Mulai riset pertamamu</h1>
        <p>
            Tulis judul atau topik penelitianmu. BacaDulu akan membantu
            sesuai jenis analisis yang kamu pilih.
        </p>
    </header>


    <section class="fr-form">

        @if($errors->any())
            <div class="alert alert-error research-composer-alert" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        @if(session('success'))
            <div class="research-composer-success" role="status">
                {{ session('success') }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('research.run') }}"
            class="research-composer research-composer--new"
            data-chat-form
        >
            @csrf

            @if($selectedSource)
                <input type="hidden" name="source_id" value="{{ $selectedSource->id }}">
            @endif

            <div class="fr-field">
                <label class="fr-label" for="fr-message">Judul / Topik</label>

                <textarea
                    id="fr-message"
                    name="message"
                    rows="2"
                    placeholder="Masukkan judul atau topik penelitian..."
                    required
                    maxlength="5000"
                    data-chat-input
                >{{ old('message') }}</textarea>
            </div>

            <div class="fr-field">
                <label class="fr-label fr-label--mobile-hidden" for="fr-category">Kategori</label>

                <div class="fr-select">
                    <select id="fr-category" name="category_id" required>
                        <option value="">Pilih kategori</option>

                        @foreach($categories as $category)
                            <option
                                value="{{ $category->id }}"
                                @selected(old('category_id', $selectedSource?->category_id) == $category->id)
                            >
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="fr-field" role="group" aria-labelledby="fr-tools-label">
                <span class="fr-label" id="fr-tools-label">Mau dibantu apa?</span>

                <div class="fr-tools">
                    @forelse($prompts as $prompt)
                        <button
                            type="button"
                            class="fr-tool"
                            data-fr-tool="{{ $prompt->id }}"
                            data-fr-tool-name="{{ $prompt->name }}"
                            aria-pressed="{{ (int) $activePrompt?->id === (int) $prompt->id ? 'true' : 'false' }}"
                        >
                            <span class="fr-tool__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    @switch($loop->index % 3)
                                        @case(0)<circle cx="10.5" cy="10.5" r="5.5"/><path d="m15 15 5 5"/>@break
                                        @case(1)<path d="M5 4h10l4 4v12H5z"/><path d="M9 12h6M9 16h4"/>@break
                                        @default<path d="M4 18h16M7 15V9M12 15V5M17 15v-4"/>
                                    @endswitch
                                </svg>
                            </span>

                            <span class="fr-tool__text">
                                <strong>{{ $prompt->name }}</strong>
                                @if($prompt->indonesian_name)
                                    <small>{{ $prompt->indonesian_name }}</small>
                                @endif
                            </span>

                            <svg class="fr-tool__check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m5 12.5 4.5 4.5L19 7.5"/>
                            </svg>
                        </button>
                    @empty
                        <p class="fr-tools__empty">Belum ada Research Tool yang aktif.</p>
                    @endforelse
                </div>

                <p class="fr-tool-desc" data-fr-tool-desc aria-live="polite">
                    {{ $activePrompt?->short_description ?: 'Pilih satu jenis analisis.' }}
                </p>

                <button type="button" class="fr-link" data-fr-tools-info>
                    Apa bedanya tiap tool?
                </button>
            </div>

            {{-- Menu pilihan prompt existing tetap menjadi sumber nilai prompt_template_id. --}}
            <div class="research-prompt-picker-wrap fr-prompt-bridge">
                <button
                    type="button"
                    class="research-prompt-picker"
                    data-prompt-open
                    aria-expanded="false"
                    aria-controls="research-prompt-menu"
                    tabindex="-1"
                >
                    <span data-prompt-label>{{ $activePrompt?->display_name ?: 'Pilih research prompt' }}</span>
                </button>

                <input
                    type="hidden"
                    name="prompt_template_id"
                    value="{{ old('prompt_template_id', $activePrompt?->id) }}"
                    data-prompt-input
                    required
                >

                @include('research.workspace.prompt-menu', [
                    'prompts' => $prompts,
                    'activePrompt' => $activePrompt,
                ])
            </div>

            <div class="fr-actions">
                <button type="button" class="fr-btn fr-btn--ghost" data-fr-demo>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M8 5.5v13l11-6.5z"/>
                    </svg>
                    Coba Contoh
                </button>

                <button type="submit" class="fr-btn fr-btn--primary" data-motion-button>
                    Mulai Research
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h13M13 6l6 6-6 6"/>
                    </svg>
                </button>
            </div>

            <p class="fr-note">
                Output AI bukan evidence akademik. Evidence tetap berasal dari source yang diretrieve.
            </p>
        </form>
    </section>


    {{-- Kolom pemandu: Bubu + mini flow. Desktop = kolom kanan; mobile = display: contents (urutan lewat grid-area). --}}
    <div class="fr-side">

    {{-- BUBU: pemandu kontekstual (4 state: search, reading, processing, verified) --}}
    <aside class="fr-guide" aria-label="Pemandu Bubu">

        <div class="fr-bubu" aria-hidden="true">
            <img data-bubu="search" class="is-on" src="{{ asset('assets/mascot/mascot-search.png') }}" alt="" width="190" height="190" decoding="async">
            <img data-bubu="reading" data-src="{{ asset('assets/mascot/mascot-reading.png') }}" alt="" width="190" height="190" decoding="async">
            <img data-bubu="processing" data-src="{{ asset('assets/mascot/mascot-processing.png') }}" alt="" width="190" height="190" decoding="async">
            <img data-bubu="verified" data-src="{{ asset('assets/mascot/mascot-success-verified.png') }}" alt="" width="190" height="190" decoding="async">
        </div>

        <div class="fr-guide__body">
            <p class="fr-guide__copy" data-fr-copy>Mulai dari judul atau topikmu.</p>

        </div>

    </aside>


    <section class="fr-flowbox" aria-label="Alur research">

        <ol class="fr-flow">
            <li data-fr-step="title"><i aria-hidden="true"></i><span>Judul</span></li>
            <li data-fr-step="tool"><i aria-hidden="true"></i><span>Research Tool</span></li>
            <li data-fr-step="source"><i aria-hidden="true"></i><span>Source</span></li>
            <li data-fr-step="reading"><i aria-hidden="true"></i><span>Reading</span></li>
            <li data-fr-step="evidence"><i aria-hidden="true"></i><span>Evidence</span></li>
            <li data-fr-step="insight"><i aria-hidden="true"></i><span>Insight</span></li>
        </ol>

        <p class="fr-sources" aria-hidden="true" data-fr-sources>
            <span>Jurnal</span>
            <span>Buku</span>
            <span>Prosiding</span>
        </p>

        <details class="fr-help">
            <summary>Bagaimana cara kerjanya?</summary>

            <ol>
                <li>Tulis topik penelitianmu.</li>
                <li>Pilih jenis analisis.</li>
                <li>BacaDulu menelusuri sumber yang relevan.</li>
                <li>Evidence dan hasil ditampilkan.</li>
                <li>Hasil bisa disimpan atau dilanjutkan menjadi dokumen.</li>
            </ol>
        </details>

    </section>

    </div>

    {{-- BUBU GUIDED CURSOR TOUR (UI murni client; dikendalikan initFirstResearch) --}}
    <div class="fr-tour" data-fr-tour hidden>

        <div class="fr-tour__spot" data-fr-spot aria-hidden="true" hidden></div>

        <div class="fr-tour__pointer" data-fr-pointer data-mode="cursor" aria-hidden="true" hidden>
            <svg class="fr-tour__cursor" viewBox="0 0 24 24" width="28" height="28">
                <path d="M3 2l15 7.6-6.4 2.1L9 18.5z" fill="#170f38" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/>
            </svg>
            <span class="fr-tour__tap"></span>
        </div>

        <section class="fr-tour__bubble" data-fr-bubble role="dialog" aria-modal="false" aria-label="Panduan Bubu" tabindex="-1">

            <div class="fr-tour__bubu" aria-hidden="true">
                <img data-fr-bubble-bubu="search" class="is-on" src="{{ asset('assets/mascot/mascot-search.png') }}" alt="" width="64" height="64" decoding="async">
                <img data-fr-bubble-bubu="reading" alt="" width="64" height="64" decoding="async">
                <img data-fr-bubble-bubu="processing" alt="" width="64" height="64" decoding="async">
                <img data-fr-bubble-bubu="verified" alt="" width="64" height="64" decoding="async">
            </div>

            <div class="fr-tour__text">
                <p class="fr-tour__copy" data-fr-bubble-copy aria-live="polite"></p>
                <p class="fr-tour__note" data-fr-bubble-note hidden></p>
            </div>

            <div class="fr-tour__foot">
                <span class="fr-tour__count" data-fr-bubble-count></span>

                <button type="button" class="fr-tour__btn fr-tour__btn--plain" data-fr-skip>Lewati</button>
                <button type="button" class="fr-tour__btn fr-tour__btn--ghost" data-fr-back>Kembali</button>
                <button type="button" class="fr-tour__btn fr-tour__btn--primary" data-fr-start>Mulai Tour</button>
                <button type="button" class="fr-tour__btn fr-tour__btn--primary" data-fr-next>Lanjut →</button>
                <button type="button" class="fr-tour__btn fr-tour__btn--primary" data-fr-keep hidden>Gunakan contoh ini</button>
                <button type="button" class="fr-tour__btn fr-tour__btn--ghost" data-fr-own hidden>Mulai dengan topik saya</button>
            </div>

        </section>

    </div>

</div>
