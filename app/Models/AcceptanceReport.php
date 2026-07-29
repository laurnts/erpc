<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Models\Concerns\LogsErpActivity;
use App\Services\Erp\Numbering\DocumentNumberAllocator;
use Database\Factories\AcceptanceReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Acceptance Report for Service requests (replaces inbound shipments).
 *
 * @property int $id
 * @property int $team_id
 * @property int $request_id
 * @property string $report_number
 * @property Carbon $reported_at
 * @property int|null $creator_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Request $request
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RequestItem> $items
 * @property-read string $created_by
 */
final class AcceptanceReport extends Model implements HasMedia
{
    use HasCreator;

    /** @use HasFactory<AcceptanceReportFactory> */
    use HasFactory;

    use HasTeam;
    use InteractsWithMedia;
    use LogsErpActivity;
    use SoftDeletes;

    /**
     * Upload directory for acceptance report attachments. The FileUpload
     * component and its AttachUploadedFiles call site must reference the
     * same value — drift between them silently drops attachments.
     */
    public const string ATTACHMENTS_UPLOAD_DIRECTORY = 'uploads-tmp/acceptance-reports';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'team_id',
        'report_number',
        'reported_at',
        'notes',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (AcceptanceReport $report): void {
            if ($report->reported_at === null) {
                $report->reported_at = now();
            }
            if ($report->team_id === null) {
                $report->team_id = $report->request->team_id;
            }
            if (empty($report->report_number)) {
                $report->report_number = self::generateReportNumber((int) $report->team_id);
            }
        });
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'reported_at' => 'date',
        ];
    }

    /**
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return [
            'report_number',
            'reported_at',
        ];
    }

    /**
     * Register media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('local')
            ->acceptsMimeTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/jpeg',
                'image/png',
                'image/gif',
            ]);
    }

    /**
     * Generate a unique report number for the given team, scoped to the current year.
     * Format: AR-{year}-{increment}
     *
     * Previously this plucked every report number for the team and year into PHP
     * to compute a max — correct, but O(rows) in memory and still raceable.
     */
    public static function generateReportNumber(int $teamId): string
    {
        $year = now()->year;

        $sequence = app(DocumentNumberAllocator::class)
            ->next($teamId, 'acceptance_report', (string) $year);

        return sprintf('AR-%d-%04d', $year, $sequence);
    }

    /**
     * The request this acceptance report is for.
     *
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * The request items included in this acceptance report.
     *
     * @return BelongsToMany<RequestItem, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(RequestItem::class, 'acceptance_report_items')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
