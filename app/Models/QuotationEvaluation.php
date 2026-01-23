<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\QuotationEvaluationObserver;
use App\Support\RomanNumerals;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Quotation Evaluation document for internal procurement documentation.
 *
 * @property int $id
 * @property int $team_id
 * @property int $request_id
 * @property string $qe_number
 * @property string|null $description
 * @property Carbon $qe_date
 * @property int|null $prepared_by_id
 * @property int|null $dept_head_sales_id
 * @property int|null $deputy_director_id
 * @property int|null $approved_by_id
 * @property string|null $dept_head_sales_name @deprecated Use dept_head_sales_id relationship instead
 * @property string|null $deputy_director_name @deprecated Use deputy_director_id relationship instead
 * @property string|null $approved_by_name @deprecated Use approved_by_id relationship instead
 * @property array<string, mixed> $data
 * @property int|null $creator_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $created_by
 */
#[ObservedBy(QuotationEvaluationObserver::class)]
final class QuotationEvaluation extends Model
{
    use HasCreator;
    use HasTeam;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'qe_number',
        'description',
        'qe_date',
        'prepared_by_id',
        'dept_head_sales_id',
        'deputy_director_id',
        'approved_by_id',
        'dept_head_sales_name', // @deprecated - kept for backward compatibility
        'deputy_director_name', // @deprecated - kept for backward compatibility
        'approved_by_name', // @deprecated - kept for backward compatibility
        'data',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'qe_date' => 'date',
            'data' => 'array',
        ];
    }

    /**
     * Generate a unique QE number for the given team.
     * Format: {increment}-DS/QE/{roman_month}/{year}
     */
    public static function generateQeNumber(int $teamId): string
    {
        $year = now()->year;
        $month = now()->month;

        $lastQe = self::where('team_id', $teamId)
            ->whereYear('created_at', $year)
            ->orderByDesc('id')
            ->first();

        $increment = 1;
        if ($lastQe !== null) {
            preg_match('/^(\d+)-/', (string) $lastQe->qe_number, $matches);
            $increment = ((int) ($matches[1] ?? 0)) + 1;
        }

        return sprintf('%03d-DS/QE/%s/%d', $increment, RomanNumerals::month($month), $year);
    }

    /**
     * The request this QE is for.
     *
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * The person who prepared the QE.
     *
     * @return BelongsTo<People, $this>
     */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(People::class, 'prepared_by_id');
    }

    /**
     * The department head of sales who approved the QE.
     *
     * @return BelongsTo<People, $this>
     */
    public function deptHeadSales(): BelongsTo
    {
        return $this->belongsTo(People::class, 'dept_head_sales_id');
    }

    /**
     * The deputy director who approved the QE.
     *
     * @return BelongsTo<People, $this>
     */
    public function deputyDirector(): BelongsTo
    {
        return $this->belongsTo(People::class, 'deputy_director_id');
    }

    /**
     * The person who approved the QE.
     *
     * @return BelongsTo<People, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(People::class, 'approved_by_id');
    }

    /**
     * Get items from the snapshot data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->data['items'] ?? [];
    }

    /**
     * Get suppliers from the snapshot data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSuppliers(): array
    {
        return $this->data['suppliers'] ?? [];
    }

    /**
     * Get request info from the snapshot data.
     *
     * @return array<string, mixed>
     */
    public function getRequestInfo(): array
    {
        return $this->data['request'] ?? [];
    }
}
