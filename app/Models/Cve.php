<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cve extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cve_id',
        'description',
        'published_date',
        'last_modified_date',
        'cvss_score',
        'severity',
        'weakness_types',
        'references',
        'source',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'published_date' => 'datetime',
        'last_modified_date' => 'datetime',
        'cvss_score' => 'decimal:1',
        'weakness_types' => 'array',
        'references' => 'array',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'cve_id';
    }

    /**
     * Get all port numbers affected by this CVE.
     */
    public function ports(): BelongsToMany
    {
        return $this->belongsToMany(Port::class, 'cve_port', 'cve_id', 'port_number', 'cve_id', 'port_number')
            ->withPivot('relevance_score')
            ->withTimestamps();
    }

    /**
     * Scope to get recent CVEs (most recent first).
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderBy('published_date', 'desc')->limit($limit);
    }

    /**
     * Scope to filter by severity.
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', strtoupper($severity));
    }

    /**
     * Scope to get critical and high severity CVEs.
     */
    public function scopeCriticalAndHigh($query)
    {
        return $query->whereIn('severity', ['CRITICAL', 'HIGH']);
    }
}
