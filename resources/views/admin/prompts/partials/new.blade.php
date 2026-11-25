<div class="bd-ix__page">
    <a href="{{ route('admin.prompts.index', array_filter(['tool' => $presetTool ?? null])) }}" class="bd-ix__back" data-ix-nav>@include('admin.tools.partials.icon', ['name' => 'arrow']) Semua instruksi</a>
    <h2 class="bd-ix__page-title">Buat instruksi</h2>
    <p class="bd-ix__muted">Instruksi baru dibuat sebagai draf v1 dan belum dipakai pengguna. Jadikan aktif setelah isinya siap.</p>

    <form method="POST" action="{{ route('admin.prompts.store') }}" class="bd-ix__form" novalidate data-ix-form data-ix-kind="create">
        @csrf
        <div class="bd-ix__grid">
            <div class="bd-ix__field">
                <label class="bd-ix__label" for="ix-f-ai_tool_id">Alat riset</label>
                <select class="bd-ix__select" id="ix-f-ai_tool_id" name="ai_tool_id" data-ix-field>
                    <option value="">Pilih alat…</option>
                    @foreach ($tools as $tool)
                        <option value="{{ $tool->id }}" @selected((string) old('ai_tool_id', $presetTool ?? '') === (string) $tool->id)>{{ $tool->name }}{{ $tool->status === 'archived' ? ' (diarsipkan)' : '' }}</option>
                    @endforeach
                </select>
                <p class="bd-ix__note" data-error-for="ai_tool_id">{{ $errors->first('ai_tool_id') }}</p>
            </div>
            <div class="bd-ix__field">
                <label class="bd-ix__label" for="ix-f-name">Nama instruksi</label>
                <input class="bd-ix__input" id="ix-f-name" name="name" maxlength="180" value="{{ old('name') }}" data-ix-field>
                <p class="bd-ix__note" data-error-for="name">{{ $errors->first('name') }}</p>
            </div>
        </div>

        @include('admin.prompts.partials.fields', ['prompt' => new \App\Models\PromptTemplate, 'readonly' => false])

        <p class="bd-ix__alert" data-ix-error role="alert" @unless ($errors->any()) hidden @endunless>{{ $errors->any() ? 'Periksa isian yang ditandai.' : '' }}</p>
        <div class="bd-ix__actions">
            <a href="{{ route('admin.prompts.index') }}" class="bd-ix__btn bd-ix__btn--quiet" data-ix-nav data-ix-cancel>Batal</a>
            <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-ix-save>Simpan sebagai draf</button>
        </div>
    </form>
</div>
