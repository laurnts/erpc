<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Standardises activity logging across ERP entities.
 *
 * Each model declares only the audit-relevant attributes; changes to those
 * are recorded on create/update/delete with the before/after values, and
 * unchanged saves are skipped. The causer and actor type are attached by the
 * ActivityLog model + causer resolver, so models stay declarative.
 */
trait LogsErpActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->activityAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Audit-relevant attributes to record for this model.
     *
     * @return list<string>
     */
    abstract protected function activityAttributes(): array;
}
