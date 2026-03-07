<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GithubInstallation extends Model
{
    /** @use HasFactory<\Database\Factories\GithubInstallationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'installation_id',
        'account_login',
        'account_type',
        'permissions',
        'events',
        'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'installation_id' => 'integer',
            'permissions' => 'array',
            'events' => 'array',
            'suspended_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
