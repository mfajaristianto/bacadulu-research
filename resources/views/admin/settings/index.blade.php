@extends('layouts.admin')

@section('title', 'Settings')
@section('page_title', 'Settings')

@section('content')

<section class="admin-page">

    <header class="bd-admin-page-head">

        <div class="bd-admin-page-head__copy">

            <p class="bd-admin-kicker">
                SYSTEM / SETTINGS
            </p>

            <h1>
                Research Runtime
            </h1>

            <p>
                Parameter operasional dapat diubah dari admin.
                Secret, API key, password, dan connector token
                tetap dikelola dari environment server.
            </p>

        </div>

    </header>

    <div class="bd-admin-note">

        <strong>
            Secret isolation
        </strong>

        <p>
            OpenAI API key:
            <strong>
                {{
                    $apiKeyConfigured
                        ? 'Configured ✓'
                        : 'Not configured'
                }}
            </strong>

            · Queue connection:
            <strong>
                {{ strtoupper($queueConnection) }}
            </strong>

            · Source Sync:
            <strong>
                {{ strtoupper($sourceSyncProvider) }}
            </strong>
        </p>

    </div>

    <form
        method="POST"
        action="{{ route('admin.settings.update') }}"
        class="bd-admin-form"
        style="margin-top:22px;"
    >

        @csrf
        @method('PUT')

        {{-- AI --}}
        <section class="bd-admin-form__section">

            <div class="bd-admin-form__section-head">

                <strong>
                    AI Runtime
                </strong>

                <p>
                    Provider dan parameter generation.
                    API key tidak pernah disimpan di database.
                </p>

            </div>

            <div class="bd-admin-form__grid">

                <label class="bd-admin-form-field">

                    <span>
                        Provider
                    </span>

                    <select
                        class="bd-admin-select"
                        name="ai_provider"
                    >
                        <option
                            value="mock"
                            @selected(
                                old(
                                    'ai_provider',
                                    data_get(
                                        $settings,
                                        'ai.provider',
                                        'mock'
                                    )
                                )
                                ===
                                'mock'
                            )
                        >
                            Mock
                        </option>

                        <option
                            value="openai"
                            @selected(
                                old(
                                    'ai_provider',
                                    data_get(
                                        $settings,
                                        'ai.provider'
                                    )
                                )
                                ===
                                'openai'
                            )
                        >
                            OpenAI
                        </option>
                    </select>

                </label>

                <label class="bd-admin-form-field">

                    <span>
                        Model
                    </span>

                    <input
                        class="bd-admin-input"
                        name="ai_model"
                        value="{{
                            old(
                                'ai_model',
                                data_get(
                                    $settings,
                                    'ai.model'
                                )
                            )
                        }}"
                        placeholder="Kosong = provider default"
                    >

                </label>

                <label class="bd-admin-form-field">

                    <span>
                        Timeout
                    </span>

                    <input
                        class="bd-admin-input"
                        type="number"
                        name="ai_timeout"
                        min="10"
                        max="300"
                        value="{{
                            old(
                                'ai_timeout',
                                data_get(
                                    $settings,
                                    'ai.timeout',
                                    90
                                )
                            )
                        }}"
                        required
                    >

                    <small>
                        Detik.
                    </small>

                </label>

                <label class="bd-admin-form-field">

                    <span>
                        Max output tokens
                    </span>

                    <input
                        class="bd-admin-input"
                        type="number"
                        name="max_output_tokens"
                        min="256"
                        max="16000"
                        value="{{
                            old(
                                'max_output_tokens',
                                data_get(
                                    $settings,
                                    'ai.max_output_tokens',
                                    3500
                                )
                            )
                        }}"
                        required
                    >

                </label>

                <label class="bd-admin-form-field">

                    <span>
                        Conversation context
                    </span>

                    <input
                        class="bd-admin-input"
                        type="number"
                        name="context_messages"
                        min="0"
                        max="30"
                        value="{{
                            old(
                                'context_messages',
                                data_get(
                                    $settings,
                                    'ai.context_messages',
                                    8
                                )
                            )
                        }}"
                        required
                    >

                    <small>
                        Jumlah message sebelumnya, bukan evidence.
                    </small>

                </label>

            </div>

        </section>

        {{-- INGESTION --}}
        <section class="bd-admin-form__section">

            <div class="bd-admin-form__section-head">

                <strong>
                    Source Ingestion
                </strong>

                <p>
                    PDF extraction dan document chunking.
                </p>

            </div>

            <div class="bd-admin-form__grid">

                <label class="bd-admin-form-field">

                    <span>
                        Upload limit KB
                    </span>

                    <input
                        class="bd-admin-input"
                        type="number"
                        name="upload_limit_kb"
                        value="{{
                            old(
                                'upload_limit_kb',
                                data_get(
                                    $settings,
                                    'ingestion.max_upload_kb',
                                    102400
                                )
                            )
                        }}"
                        required
                    >

                </label>

                <label class="bd-admin-form-field">

                    <span>
                        Chunk words
                    </span>

                    <input
                        class="bd-admin-input"
                        type="number"
                        name="chunk_words"
                        value="{{
                            old(
                                'chunk_words',
                                data_get(
                                    $settings,
                                    'ingestion.chunk_words',
                                    500
                                )
                            )
                        }}"
                        required
                    >

                </label>

                <label class="bd-admin-form-field">

                    <span>
                        Chunk overlap
                    </span>

                    <input
                        class="bd-admin-input"
                        type="number"
                        name="chunk_overlap_words"
                        value="{{
                            old(
                                'chunk_overlap_words',
                                data_get(
                                    $settings,
                                    'ingestion.chunk_overlap_words',
                                    75
                                )
                            )
                        }}"
                        required
                    >

                </label>

                <label class="bd-admin-form-field">

                    <span>
                        Minimum chunk
                    </span>

                    <input
                        class="bd-admin-input"
                        type="number"
                        name="minimum_chunk_words"
                        value="{{
                            old(
                                'minimum_chunk_words',
                                data_get(
                                    $settings,
                                    'ingestion.minimum_chunk_words',
                                    60
                                )
                            )
                        }}"
                        required
                    >

                </label>

            </div>

        </section>

        {{-- RETRIEVAL --}}
        <section class="bd-admin-form__section">

            <div class="bd-admin-form__section-head">

                <strong>
                    Retrieval
                </strong>

                <p>
                    Guard relevance dan evidence selection.
                </p>

            </div>

            <div class="bd-admin-form__grid">

                <label class="bd-admin-form-field">
                    <span>Candidate limit</span>
                    <input
                        class="bd-admin-input"
                        type="number"
                        name="candidate_limit"
                        value="{{ old('candidate_limit', data_get($settings, 'retrieval.candidate_limit', 400)) }}"
                        required
                    >
                </label>

                <label class="bd-admin-form-field">
                    <span>Evidence limit</span>
                    <input
                        class="bd-admin-input"
                        type="number"
                        name="evidence_limit"
                        value="{{ old('evidence_limit', data_get($settings, 'retrieval.evidence_limit', 8)) }}"
                        required
                    >
                </label>

                <label class="bd-admin-form-field">
                    <span>Max per document</span>
                    <input
                        class="bd-admin-input"
                        type="number"
                        name="max_per_document"
                        value="{{ old('max_per_document', data_get($settings, 'retrieval.max_per_document', 3)) }}"
                        required
                    >
                </label>

                <label class="bd-admin-form-field">
                    <span>Minimum evidence</span>
                    <input
                        class="bd-admin-input"
                        type="number"
                        name="minimum_evidence"
                        value="{{ old('minimum_evidence', data_get($settings, 'retrieval.minimum_evidence', 2)) }}"
                        required
                    >
                </label>

                <label class="bd-admin-form-field">
                    <span>Minimum score</span>
                    <input
                        class="bd-admin-input"
                        type="number"
                        step="0.01"
                        min="0"
                        max="1"
                        name="minimum_score"
                        value="{{ old('minimum_score', data_get($settings, 'retrieval.minimum_score', 0.15)) }}"
                        required
                    >
                </label>

                <label class="bd-admin-form-field">
                    <span>Excerpt length</span>
                    <input
                        class="bd-admin-input"
                        type="number"
                        name="excerpt_length"
                        value="{{ old('excerpt_length', data_get($settings, 'retrieval.excerpt_length', 900)) }}"
                        required
                    >
                </label>

            </div>

        </section>

        {{-- QUEUE --}}
        <section class="bd-admin-form__section">

            <div class="bd-admin-form__section-head">

                <strong>
                    Queue & Live Processing
                </strong>

                <p>
                    Queue connection tetap berasal dari ENV.
                    Di sini hanya nama queue dan refresh interval.
                </p>

            </div>

            <div class="bd-admin-form__grid">

                <label class="bd-admin-form-field">

                    <span>
                        Research queue
                    </span>

                    <input
                        class="bd-admin-input"
                        name="research_queue"
                        value="{{ old('research_queue', data_get($settings, 'queue.research_queue', 'research')) }}"
                        required
                    >

                </label>

                <label class="bd-admin-form-field">

                    <span>
                        Ingestion queue
                    </span>

                    <input
                        class="bd-admin-input"
                        name="ingestion_queue"
                        value="{{ old('ingestion_queue', data_get($settings, 'queue.ingestion_queue', 'ingestion')) }}"
                        required
                    >

                </label>

                <label class="bd-admin-form-field">

                    <span>
                        Live refresh
                    </span>

                    <input
                        class="bd-admin-input"
                        type="number"
                        name="live_refresh_seconds"
                        min="1"
                        max="30"
                        value="{{ old('live_refresh_seconds', data_get($settings, 'queue.live_refresh_seconds', 2)) }}"
                        required
                    >

                    <small>
                        Detik.
                    </small>

                </label>

                <label class="bd-admin-form-field">

                    <span>
                        Profile gate
                    </span>

                    <input
                        class="bd-admin-input"
                        type="number"
                        name="profile_gate"
                        min="0"
                        max="100"
                        value="{{ old('profile_gate', data_get($settings, 'access.profile_gate', 80)) }}"
                        required
                    >

                </label>

            </div>

        </section>

        {{-- DOCUMENT --}}
        <section class="bd-admin-form__section">

            <div class="bd-admin-form__section-head">

                <strong>
                    Document Defaults
                </strong>

                <p>
                    Default awal untuk document generation.
                </p>

            </div>

            <div class="bd-admin-form__grid">

                <label class="bd-admin-form-field">

                    <span>
                        Default document type
                    </span>

                    <select
                        class="bd-admin-select"
                        name="default_document_type"
                    >

                        @foreach($documentTypes as $type)

                            <option
                                value="{{ $type }}"
                                @selected(
                                    old(
                                        'default_document_type',
                                        data_get(
                                            $settings,
                                            'documents.default_type',
                                            'Research Article'
                                        )
                                    )
                                    ===
                                    $type
                                )
                            >
                                {{ $type }}
                            </option>

                        @endforeach

                    </select>

                </label>

                <label class="bd-admin-form-field">

                    <span>
                        Default citation style
                    </span>

                    <select
                        class="bd-admin-select"
                        name="default_citation_style"
                    >

                        @foreach($citationStyles as $style)

                            <option
                                value="{{ $style }}"
                                @selected(
                                    old(
                                        'default_citation_style',
                                        data_get(
                                            $settings,
                                            'documents.default_citation_style',
                                            'APA 7'
                                        )
                                    )
                                    ===
                                    $style
                                )
                            >
                                {{ $style }}
                            </option>

                        @endforeach

                    </select>

                </label>

            </div>

        </section>

        <section class="bd-admin-form__section">

            <button
                type="submit"
                class="bd-admin-button bd-admin-button--primary"
            >
                Simpan Runtime Settings
            </button>

        </section>

    </form>

</section>

@endsection