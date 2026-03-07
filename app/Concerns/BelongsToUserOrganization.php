<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToUserOrganization
{
    public function scopeForUser(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        return $query->whereIn('organization_id', $user->organizations()->pluck('organizations.id'));
    }
}
