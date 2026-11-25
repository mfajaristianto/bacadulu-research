@php
    $p = 'dp-new';
    $t = null;
    $languages = $languages ?? [];
@endphp
<div class="bd-dp__new">
    <section aria-labelledby="dp-new-blank">
        <h2 id="dp-new-blank" class="bd-ix__side-title">Template kosong</h2>
        <form method="POST" action="{{ route('admin.prompt-documents.store') }}" class="bd-ix__form" novalidate data-dp-form data-dp-dirty data-dp-kind="create">
            @csrf
            @include('admin.document-prompts.partials.template-fields')
            <p class="bd-ix__alert" data-dp-error role="alert" hidden></p>
            <div class="bd-ix__actions">
                <a href="{{ route('admin.prompt-documents.index') }}" class="bd-ix__btn bd-ix__btn--quiet" data-dp-nav>Batal</a>
                <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-save>Simpan sebagai draf</button>
            </div>
        </form>
    </section>

    <section aria-labelledby="dp-new-preset">
        <h2 id="dp-new-preset" class="bd-ix__side-title">Impor preset</h2>
        <p class="bd-ix__muted">Preset adalah titik awal yang disimpan di kode aplikasi. Anda membaca seluruh isinya dulu, lalu menyimpannya sebagai draf yang bisa diubah bebas.</p>
        <ul class="bd-dp__presets">
            @foreach ($presets as $preset)
                <li>
                    <div>
                        <strong>{{ $preset['name'] }}</strong>
                        <span class="bd-ix__sub">{{ $types[$preset['document_type']] }} · {{ count($preset['sections']) }} bagian · {{ $preset['citation_style'] }}</span>
                    </div>
                    <a href="{{ route('admin.prompt-documents.index', ['impor' => $preset['key']]) }}" class="bd-ix__btn" data-dp-nav>Baca dan impor</a>
                </li>
            @endforeach
        </ul>
    </section>
</div>
