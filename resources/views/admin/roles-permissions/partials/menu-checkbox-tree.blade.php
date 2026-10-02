@php
    $assignedLookup = array_fill_keys($assignedKeys ?? [], true);
    $recommendedLookup = array_fill_keys($recommendedKeys ?? [], true);
    $highlightRole = $highlightRole ?? null;
    $byParent = collect($catalog)->groupBy(fn ($i) => $i['parent_key'] ?? '__root__');
    $roots = $byParent->get('__root__', collect());
    $idPrefix = $idPrefix ?? 'menu';
@endphp

<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead>
            <tr>
                <th style="width: 48px;"></th>
                <th>Menu</th>
                <th style="width: 200px;">Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($roots as $item)
                @include('admin.roles-permissions.partials.menu-checkbox-row', [
                    'item' => $item,
                    'children' => $byParent->get($item['key'], collect())->filter(
                        fn ($c) => ($c['key'] ?? null) !== ($item['key'] ?? null) && (int) ($c['depth'] ?? 0) > (int) ($item['depth'] ?? 0)
                    ),
                    'byParent' => $byParent,
                    'assignedLookup' => $assignedLookup,
                    'recommendedLookup' => $recommendedLookup,
                    'highlightRole' => $highlightRole,
                    'inputName' => $inputName,
                    'idPrefix' => $idPrefix,
                ])
            @endforeach
        </tbody>
    </table>
</div>
