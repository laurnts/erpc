<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CentralPurchasingRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Jetstream\TeamInvitation as JetstreamTeamInvitation;

final class TeamInvitation extends JetstreamTeamInvitation
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'role',
        'central_purchasing_role',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'central_purchasing_role' => CentralPurchasingRole::class,
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
