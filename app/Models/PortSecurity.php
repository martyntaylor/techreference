<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortSecurity extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'port_id',
        'shodan_exposed_count',
        'shodan_updated_at',
        'censys_exposed_count',
        'censys_updated_at',
        'cve_count',
        'latest_cve',
        'cve_updated_at',
        'top_countries',
        'security_recommendations',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'shodan_exposed_count' => 'integer',
        'shodan_updated_at' => 'datetime',
        'censys_exposed_count' => 'datetime',
        'censys_updated_at' => 'datetime',
        'cve_count' => 'integer',
        'cve_updated_at' => 'datetime',
        'top_countries' => 'array',
    ];

    /**
     * Get the port that owns this security data.
     */
    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class);
    }

    /**
     * Check if Shodan data is stale (older than 24 hours).
     */
    public function isShodanStale(): bool
    {
        if (! $this->shodan_updated_at) {
            return true;
        }

        return $this->shodan_updated_at->lt(Carbon::now()->subHours(24));
    }

    /**
     * Check if Censys data is stale (older than 24 hours).
     */
    public function isCensysStale(): bool
    {
        if (! $this->censys_updated_at) {
            return true;
        }

        return $this->censys_updated_at->lt(Carbon::now()->subHours(24));
    }

    /**
     * Check if CVE data is stale (older than 24 hours).
     */
    public function isCveStale(): bool
    {
        if (! $this->cve_updated_at) {
            return true;
        }

        return $this->cve_updated_at->lt(Carbon::now()->subHours(24));
    }

    /**
     * Get total exposure count (Shodan + Censys).
     */
    public function getTotalExposureAttribute(): int
    {
        return $this->shodan_exposed_count + $this->censys_exposed_count;
    }
}
