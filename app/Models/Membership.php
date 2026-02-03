<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CentralPurchasingRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Membership as JetstreamMembership;

final class Membership extends JetstreamMembership
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'central_purchasing_role' => CentralPurchasingRole::class,
            'is_approver' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function getRoleNameAttribute(): string
    {
        // @phpstan-ignore-next-line nullCoalesce.expr
        $roleName = Jetstream::findRole($this->role)?->name ?? 'Unknown';
        
        // Append sub-role for Central Purchasing role
        if ($this->role === 'central_purchasing' && $this->central_purchasing_role) {
            $roleName .= ' - ' . $this->central_purchasing_role->getLabel();
        }
        
        return $roleName;
    }
}
