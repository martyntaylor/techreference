<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

trait Auditable
{
    /**
     * Boot the auditable trait.
     */
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            if (auth()->check()) {
                AuditLog::log('created', $model, null, $model->getAttributes());
            }
        });

        static::updated(function ($model) {
            if (auth()->check()) {
                AuditLog::log(
                    'updated',
                    $model,
                    $model->getOriginal(),
                    $model->getChanges()
                );
            }
        });

        static::deleted(function ($model) {
            if (auth()->check()) {
                AuditLog::log('deleted', $model, $model->getOriginal(), null);
            }
        });
    }

    /**
     * Get all audit logs for this model.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }
}
