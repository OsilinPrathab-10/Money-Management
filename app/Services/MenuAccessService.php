<?php

namespace App\Services;

use App\Models\RoleMenu;
use App\Models\User;
use App\Models\UserMenu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class MenuAccessService
{
    protected ?array $rawMenu = null;

    protected ?Collection $catalogCache = null;

    /** @var array<string, true> */
    protected array $usedKeys = [];

    public function menuJsonPath(): string
    {
        return base_path('resources/menu/verticalMenu.json');
    }

    public function clientMenuJsonPath(): string
    {
        return base_path('resources/menu/clientMenu.json');
    }

    /**
     * @return list<object>
     */
    public function loadRawMenu(?string $path = null): array
    {
        $path = $path ?: $this->menuJsonPath();
        $decoded = json_decode(File::get($path));

        return is_object($decoded) && isset($decoded->menu) && is_array($decoded->menu)
            ? $decoded->menu
            : [];
    }

    /**
     * Flat catalog of assignable menu items (no headers).
     *
     * @return Collection<int, array{
     *   key: string,
     *   label: string,
     *   parent_key: ?string,
     *   url: ?string,
     *   roles: list<string>,
     *   needs_admin_role: bool,
     *   depth: int
     * }>
     */
    public function catalog(): Collection
    {
        if ($this->catalogCache !== null) {
            return $this->catalogCache;
        }

        $this->usedKeys = [];
        $items = collect();
        foreach ($this->loadRawMenu() as $menu) {
            $this->walkCatalog($menu, null, [], 0, $items);
        }

        $this->catalogCache = $items->values();

        return $this->catalogCache;
    }

    /**
     * @param  list<string>  $inheritedRoles
     */
    protected function walkCatalog(object $item, ?string $parentKey, array $inheritedRoles, int $depth, Collection $items): void
    {
        if (isset($item->menuHeader)) {
            return;
        }

        $roles = $this->normalizeRoles($item->roles ?? null) ?: $inheritedRoles;
        $key = $this->uniqueKey($this->makeKey($item, $parentKey), $item, $parentKey);

        $items->push([
            'key' => $key,
            'label' => $this->displayLabel($item, $key),
            'parent_key' => $parentKey,
            'url' => isset($item->url) && is_string($item->url) ? $item->url : null,
            'slugs' => $this->normalizeSlugs($item->slug ?? null),
            'roles' => $roles,
            'needs_admin_role' => $this->needsAdminRole($roles),
            'depth' => $depth,
        ]);

        if (! empty($item->submenu) && is_array($item->submenu)) {
            foreach ($item->submenu as $sub) {
                if (is_object($sub)) {
                    $this->walkCatalog($sub, $key, $roles, $depth + 1, $items);
                }
            }
        }
    }

    protected function uniqueKey(string $base, object $item, ?string $parentKey): string
    {
        $key = $base;
        if (! isset($this->usedKeys[$key])) {
            $this->usedKeys[$key] = true;

            return $key;
        }

        $suffix = Str::slug((string) ($item->name ?? 'item'));
        if ($suffix === '') {
            $suffix = 'item';
        }

        $candidate = ($parentKey ? $parentKey.'.' : '').$suffix;
        $n = 2;
        while (isset($this->usedKeys[$candidate])) {
            $candidate = ($parentKey ? $parentKey.'.' : '').$suffix.'-'.$n;
            $n++;
        }

        $this->usedKeys[$candidate] = true;

        return $candidate;
    }

    protected function displayLabel(object $item, string $key): string
    {
        $name = (string) ($item->name ?? $key);

        return match ($key) {
            'dashboard' => 'Dashboard (Admin)',
            'agent-dashboard' => 'Dashboard (Agent)',
            'agent-collections' => 'My Collections (Agent)',
            default => $name,
        };
    }

    /**
     * Recommended menu keys for a role from verticalMenu.json role lists.
     *
     * @return list<string>
     */
    public function recommendedKeysForRole(string $roleName): array
    {
        return $this->fallbackKeysFromJsonRoles([$roleName]);
    }

    public function userCanAccessMenuKey(?User $user, string $menuKey): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        return in_array($menuKey, $this->keysForUser($user), true);
    }

    public function userCanAccessUrl(?User $user, string $url): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        $url = trim($url, '/');
        $keys = array_fill_keys($this->keysForUser($user), true);

        foreach ($this->catalog() as $item) {
            if (! $item['url'] || ! isset($keys[$item['key']])) {
                continue;
            }
            if (trim($item['url'], '/') === $url) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  mixed  $roles
     * @return list<string>
     */
    public function normalizeRoles($roles): array
    {
        if ($roles === null) {
            return [];
        }

        if ($roles instanceof \Illuminate\Support\Collection) {
            $roles = $roles->all();
        }

        if (is_object($roles) && isset($roles->roles)) {
            $roles = $roles->roles;
        }

        if (! is_array($roles)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($r) => is_string($r) ? $r : null,
            $roles
        )));
    }

    /**
     * @param  mixed  $slug
     * @return list<string>
     */
    public function normalizeSlugs($slug): array
    {
        if (is_string($slug) && $slug !== '') {
            return [$slug];
        }

        if (! is_array($slug)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($s) => is_string($s) && $s !== '' ? $s : null,
            $slug
        )));
    }

    /**
     * Human-readable menu labels for keys (assigned menus viewer).
     *
     * @param  list<string>  $keys
     * @return list<array{key:string,label:string,parent:?string}>
     */
    public function labelsForKeys(array $keys): array
    {
        $lookup = array_fill_keys($keys, true);
        $byKey = $this->catalog()->keyBy('key');

        $rows = [];
        foreach ($keys as $key) {
            $item = $byKey->get($key);
            if (! $item) {
                $rows[] = ['key' => $key, 'label' => $key, 'parent' => null];
                continue;
            }
            $parentLabel = null;
            if (! empty($item['parent_key']) && $byKey->has($item['parent_key'])) {
                $parentLabel = $byKey->get($item['parent_key'])['label'] ?? null;
            }
            $rows[] = [
                'key' => $key,
                'label' => $item['label'],
                'parent' => $parentLabel,
            ];
        }

        return $rows;
    }

    /**
     * Whether the user may open the current HTTP request based on Admin role
     * or dynamically assigned menus (role menus / user overrides).
     */
    public function userCanAccessRequest(?User $user, \Illuminate\Http\Request $request): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        $allowedKeys = $this->keysForUser($user);
        if ($allowedKeys === []) {
            return false;
        }

        $allowed = array_fill_keys($allowedKeys, true);
        $catalog = $this->catalog();
        $byKey = $catalog->keyBy('key');

        $routeName = (string) ($request->route()?->getName() ?? '');
        $path = trim($request->path(), '/');

        foreach ($catalog as $item) {
            if (! $this->itemIsAllowed($item, $allowed, $byKey)) {
                continue;
            }

            if ($this->requestMatchesMenuItem($path, $routeName, $item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, true>  $allowed
     * @param  Collection<string, array>  $byKey
     */
    protected function itemIsAllowed(array $item, array $allowed, Collection $byKey): bool
    {
        $key = $item['key'] ?? null;
        if ($key && isset($allowed[$key])) {
            return true;
        }

        // Parent module assigned ⇒ allow its children too
        $parent = $item['parent_key'] ?? null;
        $guard = 0;
        while ($parent && $guard < 10) {
            if (isset($allowed[$parent])) {
                return true;
            }
            $parent = $byKey->get($parent)['parent_key'] ?? null;
            $guard++;
        }

        return false;
    }

    protected function requestMatchesMenuItem(string $path, string $routeName, array $item): bool
    {
        if (! empty($item['url'])) {
            $url = trim((string) $item['url'], '/');
            if ($url !== '' && ($path === $url || str_starts_with($path, $url.'/'))) {
                return true;
            }
        }

        $candidates = array_merge(
            [$item['key'] ?? ''],
            $item['slugs'] ?? []
        );

        foreach ($candidates as $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }
            if ($routeName === $slug) {
                return true;
            }
            if ($routeName !== '' && (str_starts_with($routeName, $slug.'.') || str_starts_with($routeName, $slug.'-'))) {
                return true;
            }
            // Prefix slug match used in menu JSON (e.g. "client-view-")
            if (str_ends_with($slug, '-') && $routeName !== '' && str_starts_with($routeName, $slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $roles
     */
    protected function needsAdminRole(array $roles): bool
    {
        if ($roles === []) {
            return true;
        }

        $normalized = array_map('strtolower', $roles);

        return count($normalized) === 1 && in_array('admin', $normalized, true);
    }

    public function makeKey(object $item, ?string $parentKey = null): string
    {
        $base = null;

        if (isset($item->slug)) {
            if (is_string($item->slug) && $item->slug !== '') {
                $base = $item->slug;
            } elseif (is_array($item->slug)) {
                foreach ($item->slug as $slug) {
                    if (is_string($slug) && $slug !== '') {
                        $base = $slug;
                        break;
                    }
                }
            }
        }

        if (! $base && isset($item->url) && is_string($item->url) && $item->url !== '') {
            $base = str_replace(['/', '.'], ['-', '-'], trim($item->url, '/'));
        }

        if (! $base && isset($item->name) && is_string($item->name)) {
            $base = Str::slug($item->name);
        }

        $base = strtolower((string) preg_replace('/[^a-zA-Z0-9._-]+/', '-', (string) $base));
        $base = trim($base, '-._');

        if ($base === '') {
            $base = 'menu-item';
        }

        // Child keys must never equal the parent key (breaks checkbox-tree recursion).
        if ($parentKey && $base === $parentKey) {
            $suffix = Str::slug((string) ($item->name ?? 'child'));
            $base = $parentKey.'.'.($suffix !== '' ? $suffix : 'child');
        }

        return $base;
    }

    /**
     * @return list<string>
     */
    public function allKeys(): array
    {
        return $this->catalog()->pluck('key')->unique()->values()->all();
    }

    /**
     * @return list<string>
     */
    public function keysForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if ($user->hasRole('Admin')) {
            $userRoles = $user->getRoleNames()->all();
            return $this->catalog()
                ->filter(function (array $item) use ($userRoles) {
                    if (empty($item['roles'])) {
                        return true;
                    }
                    return collect($item['roles'])->intersect($userRoles)->isNotEmpty();
                })
                ->pluck('key')
                ->unique()
                ->values()
                ->all();
        }

        if ((bool) ($user->use_custom_menus ?? false)) {
            return UserMenu::where('user_id', $user->id)
                ->pluck('menu_key')
                ->unique()
                ->values()
                ->all();
        }

        $roleIds = $user->roles()->pluck('id');
        if ($roleIds->isEmpty()) {
            return $this->fallbackKeysFromJsonRoles($user->getRoleNames()->all());
        }

        $keys = RoleMenu::whereIn('role_id', $roleIds)
            ->pluck('menu_key')
            ->unique()
            ->values()
            ->all();

        // If this user's roles have no menu rows yet, use JSON role lists
        // (do not require the entire role_menus table to be empty — Admin may already be configured).
        if ($keys === []) {
            return $this->fallbackKeysFromJsonRoles($user->getRoleNames()->all());
        }

        return $keys;
    }

    /**
     * @param  list<string>  $roleNames
     * @return list<string>
     */
    public function fallbackKeysFromJsonRoles(array $roleNames): array
    {
        if ($roleNames === []) {
            return [];
        }

        return $this->catalog()
            ->filter(function (array $item) use ($roleNames) {
                return collect($item['roles'])->intersect($roleNames)->isNotEmpty();
            })
            ->pluck('key')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function keysForRole(Role|int|string $role): array
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->first();
        } elseif (is_int($role)) {
            $role = Role::find($role);
        }

        if (! $role) {
            return [];
        }

        $keys = RoleMenu::where('role_id', $role->id)->pluck('menu_key')->all();

        if ($keys === []) {
            return $this->fallbackKeysFromJsonRoles([$role->name]);
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  list<string>  $menuKeys
     */
    public function syncRoleMenus(Role $role, array $menuKeys): void
    {
        $valid = array_values(array_intersect(array_unique($menuKeys), $this->allKeys()));
        RoleMenu::where('role_id', $role->id)->delete();

        $now = now();
        $rows = array_map(fn ($key) => [
            'role_id' => $role->id,
            'menu_key' => $key,
            'created_at' => $now,
            'updated_at' => $now,
        ], $valid);

        if ($rows !== []) {
            RoleMenu::insert($rows);
        }
    }

    /**
     * @param  list<string>  $menuKeys
     */
    public function syncUserMenus(User $user, bool $useCustom, array $menuKeys = []): void
    {
        $user->use_custom_menus = $useCustom;
        $user->save();

        UserMenu::where('user_id', $user->id)->delete();

        if (! $useCustom) {
            return;
        }

        $valid = array_values(array_intersect(array_unique($menuKeys), $this->allKeys()));
        $now = now();
        $rows = array_map(fn ($key) => [
            'user_id' => $user->id,
            'menu_key' => $key,
            'created_at' => $now,
            'updated_at' => $now,
        ], $valid);

        if ($rows !== []) {
            UserMenu::insert($rows);
        }
    }

    /**
     * Annotate + filter menu tree for the given allowed keys.
     * Empty allowed keys with non-Admin user hides everything except when falling back.
     *
     * @param  list<object>  $menu
     * @param  list<string>  $allowedKeys
     * @return list<object>
     */
    public function filterMenu(array $menu, array $allowedKeys, bool $allowAll = false, ?User $user = null): array
    {
        // Must reset — catalog()/keysForUser() may have already filled usedKeys on this singleton.
        // Reusing those keys adds suffixes and breaks role_menus matching (Agent menus vanish).
        $this->usedKeys = [];

        $allowed = array_fill_keys($allowedKeys, true);
        $filtered = [];

        foreach ($menu as $item) {
            if (! is_object($item)) {
                continue;
            }

            if (isset($item->menuHeader)) {
                $filtered[] = $item;
                continue;
            }

            $key = $this->uniqueKey($this->makeKey($item, null), $item, null);
            $item->menu_key = $key;

            $children = $this->filterSubmenuItems($item->submenu ?? null, $key, $this->normalizeRoles($item->roles ?? null), $user, $allowAll, $allowed);

            $itemRoles = $this->normalizeRoles($item->roles ?? null);
            $selfAllowed = $this->isItemRoleAllowed($itemRoles, $user, $allowAll, isset($allowed[$key]));
            $hasVisibleChildren = $children !== [];

            if (! $selfAllowed && ! $hasVisibleChildren) {
                continue;
            }

            if ($hasVisibleChildren) {
                $item->submenu = $children;
            } elseif (isset($item->submenu)) {
                unset($item->submenu);
            }

            $filtered[] = $item;
        }

        return $this->pruneOrphanHeaders($filtered);
    }

    /**
     * Recursively filter submenus at any nesting depth.
     */
    protected function filterSubmenuItems(?array $submenu, string $parentKey, array $parentRoles, ?User $user, bool $allowAll, array $allowed): array
    {
        if (empty($submenu) || ! is_array($submenu)) {
            return [];
        }

        $children = [];
        foreach ($submenu as $sub) {
            if (! is_object($sub)) {
                continue;
            }
            $childKey = $this->uniqueKey($this->makeKey($sub, $parentKey), $sub, $parentKey);
            $sub->menu_key = $childKey;

            $subRoles = $this->normalizeRoles($sub->roles ?? null) ?: $parentRoles;

            $grandChildren = [];
            if (! empty($sub->submenu) && is_array($sub->submenu)) {
                $grandChildren = $this->filterSubmenuItems($sub->submenu, $childKey, $subRoles, $user, $allowAll, $allowed);
            }

            $selfAllowed = $this->isItemRoleAllowed($subRoles, $user, $allowAll, isset($allowed[$childKey]));
            $hasGrandChildren = $grandChildren !== [];

            if (! $selfAllowed && ! $hasGrandChildren) {
                continue;
            }

            if ($hasGrandChildren) {
                $sub->submenu = $grandChildren;
            } elseif (isset($sub->submenu)) {
                unset($sub->submenu);
            }

            $children[] = $sub;
        }

        return $children;
    }

    protected function isItemRoleAllowed(array $itemRoles, ?User $user, bool $allowAll, bool $inAllowedKeys): bool
    {
        if ($user && ! empty($itemRoles)) {
            $userRoles = $user->getRoleNames()->all();
            if (collect($itemRoles)->intersect($userRoles)->isEmpty()) {
                return false;
            }
        }

        return $allowAll || $inAllowedKeys;
    }

    /**
     * @param  list<object>  $menu
     * @return list<object>
     */
    protected function pruneOrphanHeaders(array $menu): array
    {
        $result = [];
        $count = count($menu);

        for ($i = 0; $i < $count; $i++) {
            $item = $menu[$i];
            if (! isset($item->menuHeader)) {
                $result[] = $item;
                continue;
            }

            $hasFollowingItem = false;
            for ($j = $i + 1; $j < $count; $j++) {
                if (isset($menu[$j]->menuHeader)) {
                    break;
                }
                $hasFollowingItem = true;
                break;
            }

            if ($hasFollowingItem) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Build filtered menuData payload for views.
     *
     * @return array{0: object, 1: object}
     */
    public function menuDataForUser(?User $user): array
    {
        $isClient = $user && $user->hasRole('Client');
        $verticalPath = $isClient ? $this->clientMenuJsonPath() : $this->menuJsonPath();
        $vertical = json_decode(File::get($verticalPath));
        $horizontal = json_decode(File::get(base_path('resources/menu/horizontalMenu.json')));

        if (! $isClient && $user && is_object($vertical) && isset($vertical->menu) && is_array($vertical->menu)) {
            $allowAll = $user->hasRole('Admin');
            $keys = $this->keysForUser($user);
            $this->usedKeys = [];
            $vertical->menu = $this->filterMenu($vertical->menu, $keys, $allowAll, $user);
        }

        return [$vertical, $horizontal];
    }

    /**
     * First navigable URL for post-login redirect.
     */
    public function firstUrlForUser(User $user): ?string
    {
        $keys = array_fill_keys($this->keysForUser($user), true);
        $allowAll = $user->hasRole('Admin');
        $catalog = $this->catalog();

        // Prefer role home screens when assigned
        $preferred = [];
        if ($user->hasRole('Agent')) {
            $preferred = ['agent-dashboard', 'agent-collections', 'client-management'];
        } elseif ($user->hasRole('Staff')) {
            // Prefer Staff-accessible pages (avoid Admin-only dashboard).
            $preferred = ['verification-kyc-verification', 'admin-account-deletion', 'chit.applications.index'];
        }

        foreach ($preferred as $prefKey) {
            if ($allowAll || isset($keys[$prefKey])) {
                $item = $catalog->firstWhere('key', $prefKey);
                if ($item && ! empty($item['url'])) {
                    return $item['url'];
                }
            }
        }

        foreach ($catalog as $item) {
            if (! $item['url']) {
                continue;
            }
            if ($allowAll || isset($keys[$item['key']])) {
                return $item['url'];
            }
        }

        return null;
    }

    /**
     * Seed role_menus from verticalMenu.json role lists.
     */
    public function backfillRoleMenusFromJson(bool $overwrite = false): int
    {
        if (! $overwrite && RoleMenu::query()->exists()) {
            return 0;
        }

        if ($overwrite) {
            RoleMenu::query()->delete();
        }

        $rolesByName = Role::query()->get()->keyBy('name');
        $inserted = 0;
        $now = now();
        $buffer = [];

        foreach ($this->catalog() as $item) {
            foreach ($item['roles'] as $roleName) {
                $role = $rolesByName->get($roleName);
                if (! $role) {
                    continue;
                }
                $buffer[] = [
                    'role_id' => $role->id,
                    'menu_key' => $item['key'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $inserted++;
            }
        }

        // Chunk insert unique pairs
        $unique = collect($buffer)->unique(fn ($r) => $r['role_id'].'|'.$r['menu_key'])->values()->all();
        foreach (array_chunk($unique, 200) as $chunk) {
            RoleMenu::insert($chunk);
        }

        return count($unique);
    }
}
