<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasSource
{
    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }
}
