<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortIssue extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'port_id',
        'issue_title',
        'symptoms',
        'solution',
        'error_code',
        'platform',
        'verified',
        'upvotes',
        'contributor_name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'verified' => 'boolean',
        'upvotes' => 'integer',
    ];

    /**
     * Get the port that owns this issue.
     */
    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class);
    }

    /**
     * Scope a query to only include verified issues.
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    /**
     * Scope a query to order by popularity (upvotes).
     */
    public function scopePopular($query)
    {
        return $query->orderBy('upvotes', 'desc');
    }

    /**
     * Scope a query to filter by platform.
     */
    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope a query to search issues.
     */
    public function scopeSearch($query, string $term)
    {
        if (config('database.default') === 'pgsql') {
            return $query->whereRaw(
                "to_tsvector('english', COALESCE(issue_title, '') || ' ' || COALESCE(symptoms, '') || ' ' || COALESCE(solution, '')) @@ plainto_tsquery('english', ?)",
                [$term]
            );
        }

        return $query->where(function ($q) use ($term) {
            $q->where('issue_title', 'LIKE', "%{$term}%")
                ->orWhere('symptoms', 'LIKE', "%{$term}%")
                ->orWhere('solution', 'LIKE', "%{$term}%");
        });
    }
}
