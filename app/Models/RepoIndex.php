<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepoIndex extends Model
{
    /** @use HasFactory<\Database\Factories\RepoIndexFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'repo_id',
        'commit_hash',
        'structural_map',
        'token_count',
    ];

    public function repo(): BelongsTo
    {
        return $this->belongsTo(Repo::class);
    }
}
