<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\NoteVisibility;
use App\Models\Concerns\HasTeam;
use Database\Factories\RequestNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A free-text note pinned to a request's timeline, optionally carrying file
 * attachments in the 'note_attachments' media collection.
 *
 * Unlike the activity log, a note is authored content surfaced directly on
 * the timeline; its {@see NoteVisibility} decides which portal side (if any)
 * may read it, and {@see $audience_company_id} pins a supplier-shared note to
 * exactly one supplier company so a shared request never leaks one supplier's
 * note to another. request_id stays out of $fillable ($guarded is empty
 * instead) so this note — which is deliberately NOT activity-logged — is not
 * mistaken for a logged request child by the timeline allow-list arch test.
 *
 * @property int $id
 * @property int $team_id
 * @property int $request_id
 * @property int|null $author_id
 * @property ActorType $author_actor_type
 * @property string|null $body
 * @property NoteVisibility $visibility
 * @property int|null $audience_company_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Request $request
 * @property-read User|null $author
 * @property-read Company|null $audienceCompany
 */
final class RequestNote extends Model implements HasMedia
{
    /** @use HasFactory<RequestNoteFactory> */
    use HasFactory;

    use HasTeam;
    use InteractsWithMedia;

    /**
     * Media collection holding a note's file attachments.
     */
    public const string ATTACHMENTS_COLLECTION = 'note_attachments';

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'author_actor_type' => ActorType::class,
            'visibility' => NoteVisibility::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Register media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ATTACHMENTS_COLLECTION)
            ->useDisk('local');
    }

    /**
     * The request this note is pinned to.
     *
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * The user who authored this note (null once the user is deleted).
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The single supplier company a supplier-shared note is scoped to.
     *
     * @return BelongsTo<Company, $this>
     */
    public function audienceCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'audience_company_id');
    }
}
