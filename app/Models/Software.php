<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Software extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'category',
        'vendor',
        'website_url',
        'description',
        'latest_version',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Generate slug from name before creating.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($software) {
            if (empty($software->slug)) {
                $software->slug = Str::slug($software->name);
            }
        });
    }

    /**
     * Get the ports that use this software.
     */
    public function ports(): BelongsToMany
    {
        return $this->belongsToMany(Port::class, 'port_software')
            ->withPivot('is_default', 'config_notes')
            ->withTimestamps();
    }

    /**
     * Get ports where this is the default software.
     */
    public function defaultPorts(): BelongsToMany
    {
        return $this->belongsToMany(Port::class, 'port_software')
            ->wherePivot('is_default', true)
            ->withTimestamps();
    }

    /**
     * Scope a query to only include active software.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
