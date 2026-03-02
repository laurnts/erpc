<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Database\Factories\AcceptanceReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Acceptance Report for Service requests (replaces inbound shipments).
 *
 * @property int $id
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
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
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

        static::creating(function (AcceptanceReport $report): void {
            if ($report->reported_at === null) {
                $report->reported_at = now();
            }
            if (empty($report->report_number)) {
                $report->report_number = self::generateReportNumber($report->request_id);
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
     * Register media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
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
     * Generate a unique report number for the given request.
     * Format: AR-{year}-{increment}
     */
    public static function generateReportNumber(int $requestId): string
    {
        $year = now()->year;
        $request = Request::findOrFail($requestId);

        $lastReport = self::where('request_id', $requestId)
            ->whereYear('created_at', $year)
            ->orderByDesc('id')
            ->first();

        $increment = 1;
        if ($lastReport !== null) {
            preg_match('/AR-\d+-(\d+)$/', (string) $lastReport->report_number, $matches);
            $increment = ((int) ($matches[1] ?? 0)) + 1;
        }

        return sprintf('AR-%d-%04d', $year, $increment);
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
