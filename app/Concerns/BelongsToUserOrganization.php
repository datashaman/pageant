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

    public function scopeForCurrentOrganization(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        $orgId = $user->currentOrganizationId();

        if ($orgId) {
            return $query->where('organization_id', $orgId);
        }

        return $query->whereIn('organization_id', $user->organizations()->pluck('organizations.id'));
    }
}
