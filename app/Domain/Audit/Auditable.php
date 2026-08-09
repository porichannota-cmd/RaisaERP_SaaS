<?php

namespace App\Domain\Audit;

use App\Domain\Tenant\ActiveTenantContext;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            $model->audit('created', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $model->audit('updated', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function (Model $model) {
            $model->audit('deleted', $model->getAttributes(), null);
        });
    }

    protected function audit(string $event, ?array $oldValues, ?array $newValues): void
    {
        // Don't audit if no tenant context is available (e.g. initial setup)
        if (!ActiveTenantContext::isSet()) {
            return;
        }

        AuditLog::create([
            'tenant_id' => ActiveTenantContext::get(),
            'user_id' => auth()->id(),
            'event_type' => $event,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'correlation_id' => request()->header('X-Correlation-ID'),
        ]);
    }
}
