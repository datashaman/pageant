<?php

namespace App\Models;

use App\Concerns\BelongsToUserOrganization;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Agent extends Model
{
    /** @use HasFactory<\Database\Factories\AgentFactory> */
    use BelongsToUserOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'name',
        'enabled',
        'description',
        'tools',
        'events',
        'provider',
        'model',
        'permission_mode',
        'max_turns',
        'background',
        'isolation',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'tools' => 'array',
            'events' => 'array',
            'background' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function modelDisplayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->model === 'inherit' ? 'Default' : $this->model);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    public function repos(): BelongsToMany
    {
        return $this->belongsToMany(Repo::class);
    }
}
