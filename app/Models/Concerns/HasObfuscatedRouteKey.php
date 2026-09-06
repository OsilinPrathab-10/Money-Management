<?php

namespace App\Models\Concerns;

use App\Support\HashId;
use Illuminate\Database\Eloquent\Model;

/**
 * Use Hashids in route URLs instead of raw integer IDs.
 * Old numeric URLs still resolve for bookmarks during transition.
 *
 * @mixin Model
 */
trait HasObfuscatedRouteKey
{
    public function getRouteKey(): mixed
    {
        $key = $this->getAttribute($this->getRouteKeyName());

        if ($key === null) {
            return parent::getRouteKey();
        }

        return HashId::encode($key);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();
        $id = HashId::decode((string) $value);
        if ($id === null) {
            if (ctype_digit((string) $value)) {
                $id = (int) $value;
            } else {
                abort(404);
            }
        }

        return static::where($field, $id)->firstOrFail();
    }
}
