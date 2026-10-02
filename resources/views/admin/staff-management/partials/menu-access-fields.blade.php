@php
    $menuCatalog = $menuCatalog ?? collect();
    $assignedKeys = $assignedKeys ?? [];
    $menuMode = $menuMode ?? 'inherit';
    $prefix = $prefix ?? 'menu';
@endphp
<div class="col-12" id="{{ $prefix }}_access_section">
    <div class="border rounded p-3 bg-label-secondary bg-opacity-25">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h6 class="fw-bold mb-1"><i class="ri-menu-fold-line me-1"></i> Menu access</h6>
                                <small class="text-muted">Inherit role menus (Agent defaults from Menu Access), or pick a custom list for this person. Assign <strong>Admin</strong> role only for full system access.</small>
            </div>
            <a href="{{ route('roles.menus', ['role' => 'Staff']) }}" target="_blank" class="btn btn-xs btn-outline-primary">
                Edit Staff menus
            </a>
            <a href="{{ route('roles.menus', ['role' => 'Agent']) }}" target="_blank" class="btn btn-xs btn-outline-success">
                Edit Agent menus
            </a>
        </div>
        <div class="d-flex flex-wrap gap-3 mb-3">
            <div class="form-check">
                <input class="form-check-input menu-mode-radio" type="radio" name="menu_mode" id="{{ $prefix }}_mode_inherit" value="inherit" @checked($menuMode !== 'custom') data-target="{{ $prefix }}_custom_box">
                <label class="form-check-label" for="{{ $prefix }}_mode_inherit">Inherit from role</label>
            </div>
            <div class="form-check">
                <input class="form-check-input menu-mode-radio" type="radio" name="menu_mode" id="{{ $prefix }}_mode_custom" value="custom" @checked($menuMode === 'custom') data-target="{{ $prefix }}_custom_box">
                <label class="form-check-label" for="{{ $prefix }}_mode_custom">Custom menus for this person</label>
            </div>
        </div>
        <div id="{{ $prefix }}_custom_box" class="{{ $menuMode === 'custom' ? '' : 'd-none' }}" style="max-height: 280px; overflow: auto;">
            @include('admin.roles-permissions.partials.menu-checkbox-tree', [
                'catalog' => $menuCatalog,
                'assignedKeys' => $assignedKeys,
                'inputName' => 'menus[]',
                'idPrefix' => $prefix,
            ])
        </div>
    </div>
</div>
