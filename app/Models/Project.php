<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\ProjectObserver;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;

/**
 * @property string $project_number
 * @property string $name
 * @property string|null $description
 * @property int|null $buyer_id
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property ProjectStatus $status
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $deleted_at
 * @property-read Company|null $buyer
 */
#[ObservedBy(ProjectObserver::class)]
final class Project extends Model implements HasCustomFields
{
    use HasCreator;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasTeam;
    use SoftDeletes;
    use UsesCustomFields;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_number',
        'name',
        'description',
        'buyer_id',
        'start_date',
        'end_date',
        'status',
        'notes',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'is_active' => true,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => ProjectStatus::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * The buyer (company) associated with this project.
     *
     * @return BelongsTo<Company, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_id');
    }
}
