<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityType;
use App\Models\Concerns\HasTeam;
use Database\Factories\RequestActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $request_id
 * @property int|null $user_id
 * @property ActivityType $activity_type
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Request $request
 * @property-read User|null $user
 * @property-read Model|null $subject
 */
final class RequestActivity extends Model
{
    /** @use HasFactory<RequestActivityFactory> */
    use HasFactory;

    use HasTeam;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'user_id',
        'activity_type',
        'description',
        'subject_type',
        'subject_id',
        'metadata',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'activity_type' => ActivityType::class,
            'metadata' => 'array',
        ];
    }

    /**
     * The request this activity belongs to.
     *
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * The user who performed the activity.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The subject entity that triggered this activity.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
