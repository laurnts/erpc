<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PnlStatus;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\ProfitAndLossObserver;
use App\Support\RomanNumerals;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Profit and Loss document for internal tracking.
 *
 * @property int $id
 * @property int $team_id
 * @property int $request_id
 * @property int|null $buyer_quote_id
 * @property string $pnl_number
 * @property string|null $description
 * @property Carbon $pnl_date
 * @property int|null $prepared_by_id
 * @property int|null $dept_head_sales_id
 * @property int|null $deputy_director_id
 * @property int|null $approved_by_id
 * @property string|null $dept_head_sales_name @deprecated Use dept_head_sales_id relationship instead
 * @property string|null $deputy_director_name @deprecated Use deputy_director_id relationship instead
 * @property string|null $approved_by_name @deprecated Use approved_by_id relationship instead
 * @property array<string, mixed>|null $data
 * @property int|null $creator_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $created_by
 * @property-read PnlStatus $status
 */
#[ObservedBy(ProfitAndLossObserver::class)]
final class ProfitAndLoss extends Model
{
    use HasCreator;
    use HasTeam;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'buyer_quote_id',
        'pnl_number',
        'description',
        'pnl_date',
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
            'pnl_date' => 'date',
            'data' => 'array',
        ];
    }

    /**
     * Generate a unique PNL number for the given team.
     * Format: {4digit increment}/EL-PNL/{roman_month}/{year}
     */
    public static function generatePnlNumber(int $teamId): string
    {
        $year = now()->year;
        $month = now()->month;

        $lastPnl = self::where('team_id', $teamId)
            ->whereYear('created_at', $year)
            ->orderByDesc('id')
            ->first();

        $increment = 1;
        if ($lastPnl !== null) {
            preg_match('/^(\d+)\//', (string) $lastPnl->pnl_number, $matches);
            $increment = ((int) ($matches[1] ?? 0)) + 1;
        }

        return sprintf('%04d/EL-PNL/%s/%d', $increment, RomanNumerals::month($month), $year);
    }

    /**
     * The request this PNL is for.
     *
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * The buyer quote this PNL is for.
     *
     * @return BelongsTo<BuyerQuote, $this>
     */
    public function buyerQuote(): BelongsTo
    {
        return $this->belongsTo(BuyerQuote::class);
    }

    /**
     * The person who prepared the PNL.
     *
     * @return BelongsTo<People, $this>
     */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(People::class, 'prepared_by_id');
    }

    /**
     * The department head of sales who approved the PNL.
     *
     * @return BelongsTo<People, $this>
     */
    public function deptHeadSales(): BelongsTo
    {
        return $this->belongsTo(People::class, 'dept_head_sales_id');
    }

    /**
     * The deputy director who approved the PNL.
     *
     * @return BelongsTo<People, $this>
     */
    public function deputyDirector(): BelongsTo
    {
        return $this->belongsTo(People::class, 'deputy_director_id');
    }

    /**
     * The person who approved the PNL.
     *
     * @return BelongsTo<People, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(People::class, 'approved_by_id');
    }

    /**
     * Get the computed status based on whether the request has buyer orders.
     *
     * @return Attribute<PnlStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (): PnlStatus {
                $hasBuyerOrders = $this->request?->buyerOrders()->exists() ?? false;

                return $hasBuyerOrders ? PnlStatus::ORDERED : PnlStatus::PENDING;
            },
        );
    }
}
