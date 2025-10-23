<?php

namespace App\Observers;

use App\Models\Port;
use Illuminate\Support\Facades\Cache;

class PortObserver
{
    /**
     * Handle the Port "saving" event (before create/update).
     */
    public function saving(Port $port): void
    {
        // Update search vector for PostgreSQL full-text search
        if (config('database.default') === 'pgsql' && ($port->isDirty('service_name') || $port->isDirty('description'))) {
            // The tsvector will be automatically updated by PostgreSQL triggers
            // Or we can set it manually here if needed
            // For now, relying on the GIN index created in migration
        }
    }

    /**
     * Handle the Port "created" event.
     */
    public function created(Port $port): void
    {
        // Clear relevant caches
        $this->clearPortCaches($port);

        // TODO: Trigger sitemap regeneration (will be implemented later)
        // event(new PortCreated($port));
    }

    /**
     * Handle the Port "updated" event.
     */
    public function updated(Port $port): void
    {
        // Invalidate caches only when content affecting views/filters changes
        if ($port->wasChanged(['service_name', 'description', 'protocol', 'transport_protocol', 'risk_level', 'iana_official'])) {
            $this->clearPortCaches($port);
        }

        // Popularity cache can be handled separately
        if ($port->wasChanged('view_count')) {
            Cache::forget('ports:popular');
        }

        // Refresh materialized views if significant changes
        if ($port->wasChanged(['risk_level', 'iana_official'])) {
            // TODO: Queue job to refresh materialized views
            // RefreshMaterializedViews::dispatch();
        }
    }

    /**
     * Handle the Port "deleted" event.
     */
    public function deleted(Port $port): void
    {
        // Clear relevant caches
        $this->clearPortCaches($port);

        // TODO: Trigger sitemap regeneration
        // event(new PortDeleted($port));
    }

    /**
     * Clear all caches related to this port.
     */
    protected function clearPortCaches(Port $port): void
    {
        // Clear individual port cache
        Cache::forget("port:{$port->port_number}");

        // Clear category caches
        foreach ($port->categories as $category) {
            Cache::forget("category:{$category->slug}");
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags(['category', "category:{$category->id}"])->flush();
            }
        }

        // Clear popular ports cache
        Cache::forget('ports:popular');

        // Clear search result caches (use tags if supported)
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags(['ports', 'search'])->flush();

            // Clear HTTP response caches for this port
            Cache::tags(['http', "port:{$port->port_number}"])->flush();

            // Clear HTTP response caches for affected categories
            foreach ($port->categories as $category) {
                Cache::tags(['http', "category:{$category->slug}"])->flush();
            }

            // Clear ports home page cache (popular ports list)
            Cache::tags(['http', 'ports:home'])->flush();

            // Clear port range caches (if port is in common ranges)
            Cache::tags(['http', 'ports:range'])->flush();
        }
    }
}
