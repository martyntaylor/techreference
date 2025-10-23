<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

trait Auditable
{
    /**
     * Sensitive fields that should be redacted from audit logs.
     *
     * @var list<string>
     */
    protected array $auditRedact = [
        'password',
        'remember_token',
        'api_key',
        'secret',
        'token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'api_token',
        'access_token',
        'refresh_token',
    ];

    /**
     * Redact sensitive fields from audit log values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function redactForAudit(array $values): array
    {
        foreach ($this->auditRedact as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[REDACTED]';
            }
        }

        return $values;
    }

    /**
     * Boot the auditable trait.
     */
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            if (auth()->check()) {
                DB::afterCommit(function () use ($model) {
                    AuditLog::log('created', $model, null, $model->redactForAudit($model->getAttributes()));
                });
            }
        });

        static::updated(function ($model) {
            if (auth()->check()) {
                $original = $model->redactForAudit($model->getOriginal());
                $changes = $model->redactForAudit($model->getChanges());

                DB::afterCommit(function () use ($model, $original, $changes) {
                    AuditLog::log('updated', $model, $original, $changes);
                });
            }
        });

        static::deleted(function ($model) {
            if (auth()->check()) {
                $original = $model->redactForAudit($model->getOriginal());

                DB::afterCommit(function () use ($model, $original) {
                    AuditLog::log('deleted', $model, $original, null);
                });
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
