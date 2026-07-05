<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PortalMembershipState;
use App\Enums\PortalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $company_id
 * @property int|null $user_id
 * @property PortalType $portal
 * @property int|null $invited_by
 * @property bool $is_active
 * @property string|null $invited_name
 * @property string|null $invited_email
 */
final class CompanyPortalUser extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'company_id',
        'user_id',
        'portal',
        'invited_by',
        'is_active',
        'invited_name',
        'invited_email',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'portal' => PortalType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Derived lifecycle state: never stored, so it cannot drift from the
     * columns access checks actually read.
     */
    public function state(): PortalMembershipState
    {
        if ($this->user_id === null) {
            return PortalMembershipState::Invited;
        }

        return $this->is_active ? PortalMembershipState::Active : PortalMembershipState::Deactivated;
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
