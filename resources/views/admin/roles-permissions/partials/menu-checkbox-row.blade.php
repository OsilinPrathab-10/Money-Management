@php
    $checked = isset($assignedLookup[$item['key']]);
    $hasChildren = $children->isNotEmpty();
    $idPrefix = $idPrefix ?? 'menu';
    $inputId = $idPrefix . '_' . md5($item['key']);
    $isRecommended = isset($recommendedLookup[$item['key']]);
    $roles = $item['roles'] ?? [];
@endphp
<tr class="{{ ($item['depth'] ?? 0) > 0 ? 'table-light' : '' }} {{ $isRecommended ? '' : '' }}">
    <td>
        <div class="form-check mb-0">
            <input
                class="form-check-input menu-access-checkbox"
                type="checkbox"
                name="{{ $inputName }}"
                value="{{ $item['key'] }}"
                id="{{ $inputId }}"
                @checked($checked)
                @if($hasChildren) data-menu-parent="{{ $item['key'] }}" @endif
                @if(!empty($item['parent_key'])) data-menu-child-of="{{ $item['parent_key'] }}" @endif
            >
        </div>
    </td>
    <td>
        <label class="form-check-label {{ ($item['depth'] ?? 0) > 0 ? 'ps-3' : 'fw-semibold' }}" for="{{ $inputId }}">
            @if(($item['depth'] ?? 0) > 0)
                <i class="ri-corner-down-right-line text-muted me-1"></i>
            @endif
            {{ $item['label'] }}
        </label>
        <div class="{{ ($item['depth'] ?? 0) > 0 ? 'ps-3' : '' }}">
            @foreach($roles as $roleLabel)
                <span class="badge bg-label-{{ $roleLabel === 'Agent' ? 'success' : ($roleLabel === 'Staff' ? 'primary' : 'secondary') }} xsmall" style="font-size: 10px;">{{ $roleLabel }}</span>
            @endforeach
            @if($isRecommended && $highlightRole)
                <span class="badge bg-label-info xsmall" style="font-size: 10px;">Recommended</span>
            @endif
        </div>
    </td>
    <td>
        @if(!empty($item['needs_admin_role']))
            <span class="badge bg-label-warning" title="This page is behind Admin-only routes. Assign the Admin role for full open access.">
                Needs Admin role to open
            </span>
        @elseif(in_array('Agent', $roles, true) && count($roles) === 1)
            <span class="text-success small">Agent app menu</span>
        @else
            <span class="text-muted small">—</span>
        @endif
    </td>
</tr>
@foreach($children as $child)
    @if(($child['key'] ?? null) !== ($item['key'] ?? null) && (int) ($child['depth'] ?? 0) > (int) ($item['depth'] ?? 0))
        @include('admin.roles-permissions.partials.menu-checkbox-row', [
            'item' => $child,
            'children' => $byParent->get($child['key'], collect())->filter(
                fn ($c) => ($c['key'] ?? null) !== ($child['key'] ?? null) && (int) ($c['depth'] ?? 0) > (int) ($child['depth'] ?? 0)
            ),
            'byParent' => $byParent,
            'assignedLookup' => $assignedLookup,
            'recommendedLookup' => $recommendedLookup,
            'highlightRole' => $highlightRole,
            'inputName' => $inputName,
            'idPrefix' => $idPrefix,
        ])
    @endif
@endforeach
