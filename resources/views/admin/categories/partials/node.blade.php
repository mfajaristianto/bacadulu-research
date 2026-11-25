{{-- Satu kategori dan anak-anaknya (rekursif). $node = ['category', 'context', 'children'], $depth, $names, $descendants. --}}
@php
    $c = $node['category'];
    $kids = $node['children'];
    $blocked = implode(',', [$c->id, ...($descendants[$c->id] ?? [])]);
    $labels = ['active' => 'Aktif', 'archived' => 'Diarsipkan'];
@endphp
<li class="bd-cat__node" data-cat-node data-id="{{ $c->id }}">
    <div class="bd-cat__row {{ $node['context'] ? 'is-context' : '' }}" style="--depth: {{ $depth }}">
        <div class="bd-cat__name">
            @if (count($kids))
                <button type="button" class="bd-cat__twist" aria-expanded="true" aria-controls="cat-kids-{{ $c->id }}" aria-label="Ciutkan subkategori {{ $c->name }}" data-cat-twist data-name="{{ $c->name }}">@include('admin.categories.partials.icon', ['name' => 'chevron-down'])</button>
            @else
                <span class="bd-cat__twist-gap" aria-hidden="true"></span>
            @endif
            <div class="bd-cat__text">
                <strong class="bd-cat__title">{{ $c->name }}</strong>
                <span class="bd-cat__meta">
                    {{ $c->slug }}
                    @if ($c->parent_id) · subkategori dari {{ $names[$c->parent_id] ?? '—' }}@endif
                    @if ($c->children_count) · {{ $c->children_count }} subkategori @endif
                </span>
            </div>
        </div>
        <span class="bd-cat__count">@if ($c->documents_count)<a href="{{ route('admin.library.index', ['category' => $c->id]) }}" title="Lihat sumber di Pustaka">{{ number_format($c->documents_count, 0, ',', '.') }} sumber</a>@else<span class="bd-cat__zero">0 sumber</span>@endif</span>
        <span class="bd-cat__state bd-cat__state--{{ $c->status }}">{{ $labels[$c->status] ?? $c->status }}</span>
        <div class="bd-cat__actions">
            <a href="{{ route('admin.categories.edit', $c) }}" class="bd-cat__btn bd-cat__btn--quiet bd-cat__btn--small" data-cat-edit
               data-id="{{ $c->id }}" data-name="{{ $c->name }}" data-slug="{{ $c->slug }}" data-parent="{{ $c->parent_id }}" data-status="{{ $c->status }}" data-blocked="{{ $blocked }}" data-sources="{{ $c->documents_count }}">Edit<span class="bd-cat__sr"> {{ $c->name }}</span></a>
        </div>
    </div>

    @if (count($kids))
        <div class="bd-cat__kids" id="cat-kids-{{ $c->id }}">
            <ul>
                @foreach ($kids as $child)
                    @include('admin.categories.partials.node', ['node' => $child, 'depth' => $depth + 1])
                @endforeach
            </ul>
        </div>
    @endif
</li>
