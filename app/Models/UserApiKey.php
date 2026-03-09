<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserApiKey extends Model
{
    /** @use HasFactory<\Database\Factories\UserApiKeyFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'provider',
        'api_key',
        'is_valid',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_valid' => 'boolean',
            'validated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_valid', true);
    }
}
