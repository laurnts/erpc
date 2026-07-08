<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PortalRegistrationStatus;
use App\Models\Concerns\HasTeam;
use Database\Factories\PortalRegistrationRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pending buyer self-registration application (design D4). Holds the
 * applicant's details and hashed password until staff approve (creating the
 * buyer Company + User + buyer portal membership) or reject. No User,
 * Company, or portal-access records exist before approval.
 *
 * @property int $team_id
 * @property string $name
 * @property string $email
 * @property string $company_name
 * @property string|null $phone
 * @property string|null $message
 * @property string $password
 * @property PortalRegistrationStatus $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 */
final class PortalRegistrationRequest extends Model
{
    /** @use HasFactory<PortalRegistrationRequestFactory> */
    use HasFactory;

    use HasTeam;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'name',
        'email',
        'company_name',
        'phone',
        'message',
        'password',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => PortalRegistrationStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === PortalRegistrationStatus::Pending;
    }
}
