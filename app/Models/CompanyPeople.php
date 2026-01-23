<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for company_people relationship.
 *
 * @property int $id
 * @property int $company_id
 * @property int $people_id
 * @property ContactRole|null $role
 * @property bool $is_primary
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class CompanyPeople extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'company_people';

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'role' => ContactRole::class,
            'is_primary' => 'boolean',
        ];
    }
}
