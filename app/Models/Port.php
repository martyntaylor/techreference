<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read array<int,string> $protocols Dynamically set list of protocols for this port number
 */
class Port extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'port_number',
        'protocol',
        'service_name',
        'transport_protocol',
        'description',
        'iana_official',
        'iana_status',
        'iana_updated_at',
        'data_source',
        'risk_level',
        'security_notes',
        'encrypted_default',
        'common_uses',
        'historical_context',
        'view_count',
        'search_vector',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'port_number' => 'integer',
        'iana_official' => 'boolean',
        'encrypted_default' => 'boolean',
        'iana_updated_at' => 'datetime',
        'view_count' => 'integer',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'port_number';
    }

    /**
     * Get the display name for the port.
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => sprintf(
                'Port %d (%s)%s',
                $this->port_number,
                $this->protocol,
                $this->service_name ? ' - ' . $this->service_name : ''
            ),
        );
    }

    /**
     * Get the formatted risk level with color.
     */
    protected function riskColor(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->risk_level) {
                'High' => 'red',
                'Medium' => 'yellow',
                'Low' => 'green',
                default => 'gray',
            },
        );
    }

    /**
     * Scope a query to filter by risk level.
     */
    public function scopeByRisk($query, string $riskLevel)
    {
        return $query->where('risk_level', $riskLevel);
    }

    /**
     * Scope a query to filter by protocol.
     */
    public function scopeByProtocol($query, string $protocol)
    {
        return $query->where('protocol', $protocol);
    }

    /**
     * Scope a query to get all protocols for a given port number.
     */
    public function scopeForPortNumber($query, int $portNumber)
    {
        return $query->where('port_number', $portNumber);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('categories.id', $categoryId);
        });
    }

    /**
     * Scope a query to search ports.
     */
    public function scopeSearch($query, string $term)
    {
        if (config('database.default') === 'pgsql') {
            return $query->whereRaw(
                "to_tsvector('english', COALESCE(service_name, '') || ' ' || COALESCE(description, '')) @@ plainto_tsquery('english', ?)",
                [$term]
            );
        }

        // Fallback for non-PostgreSQL databases
        return $query->where(function ($q) use ($term) {
            $q->where('service_name', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%")
                ->orWhere('port_number', $term);
        });
    }

    /**
     * Scope a query to search ports with ranking.
     */
    public function scopeSearchRanked($query, string $term)
    {
        if (config('database.default') === 'pgsql') {
            return $query->selectRaw(
                "*, ts_rank(to_tsvector('english', COALESCE(service_name, '') || ' ' || COALESCE(description, '')), plainto_tsquery('english', ?)) as rank",
                [$term]
            )->whereRaw(
                "to_tsvector('english', COALESCE(service_name, '') || ' ' || COALESCE(description, '')) @@ plainto_tsquery('english', ?)",
                [$term]
            )->orderBy('rank', 'desc');
        }

        return $query->search($term);
    }

    /**
     * Scope a query to only include official IANA ports.
     */
    public function scopeOfficial($query)
    {
        return $query->where('iana_official', true);
    }

    /**
     * Scope a query to only include encrypted ports.
     */
    public function scopeEncrypted($query)
    {
        return $query->where('encrypted_default', true);
    }

    /**
     * Scope a query to filter by data source.
     */
    public function scopeByDataSource($query, string $dataSource)
    {
        return $query->where('data_source', $dataSource);
    }

    /**
     * Scope a query to get popular ports.
     */
    public function scopePopular($query, int $limit = 10)
    {
        return $query->orderBy('view_count', 'desc')->limit($limit);
    }

    /**
     * Increment the view count for this port.
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Get the software that uses this port.
     */
    public function software(): BelongsToMany
    {
        return $this->belongsToMany(Software::class, 'port_software')
            ->withPivot('is_default', 'config_notes')
            ->withTimestamps();
    }

    /**
     * Get the security information for this port number.
     * Note: Security data is shared across all protocols (TCP, UDP, SCTP) for the same port_number.
     */
    public function security(): HasOne
    {
        return $this->hasOne(PortSecurity::class, 'port_number', 'port_number');
    }

    /**
     * Get the configuration examples for this port.
     */
    public function configs(): HasMany
    {
        return $this->hasMany(PortConfig::class);
    }

    /**
     * Get verified configs only.
     */
    public function verifiedConfigs(): HasMany
    {
        return $this->hasMany(PortConfig::class)->where('verified', true);
    }

    /**
     * Get the common issues for this port.
     */
    public function issues(): HasMany
    {
        return $this->hasMany(PortIssue::class);
    }

    /**
     * Get verified issues only.
     */
    public function verifiedIssues(): HasMany
    {
        return $this->hasMany(PortIssue::class)
            ->where('verified', true)
            ->orderBy('upvotes', 'desc');
    }

    /**
     * Get related ports.
     */
    public function relatedPorts(): BelongsToMany
    {
        return $this->belongsToMany(
            Port::class,
            'port_relations',
            'port_id',
            'related_port_id'
        )->withPivot('relation_type', 'description')
            ->withTimestamps();
    }

    /**
     * Get ports that are related to this one (inverse relationship).
     */
    public function relatedBy(): BelongsToMany
    {
        return $this->belongsToMany(
            Port::class,
            'port_relations',
            'related_port_id',
            'port_id'
        )->withPivot('relation_type', 'description')
            ->withTimestamps();
    }

    /**
     * Get the categories for this port.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'port_categories')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get the primary category for this port.
     */
    public function primaryCategory(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'port_categories')
            ->wherePivot('is_primary', true)
            ->withTimestamps();
    }

    /**
     * Get CVEs for this port number (via pivot table).
     */
    public function cves(): BelongsToMany
    {
        return $this->belongsToMany(Cve::class, 'cve_port', 'port_number', 'cve_id', 'port_number', 'cve_id')
            ->withPivot('relevance_score')
            ->withTimestamps()
            ->orderBy('published_date', 'desc');
    }
}
