<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PortalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property int $company_id
 * @property string $email
 * @property string $name
 * @property PortalType $portal
 * @property int $invited_by
 * @property string $token
 * @property Carbon|null $accepted_at
 */
final class PortalInvitation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'company_id',
        'email',
        'name',
        'portal',
        'invited_by',
        'token',
        'accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'portal' => PortalType::class,
            'accepted_at' => 'datetime',
        ];
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function markAccepted(): void
    {
        $this->accepted_at = now();
        $this->save();
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
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
