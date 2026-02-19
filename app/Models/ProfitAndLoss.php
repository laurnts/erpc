<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CentralPurchasingRole;
use App\Enums\PNLStatus;
use App\Services\TeamMemberService;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\ProfitAndLossObserver;
use App\Support\RomanNumerals;
use Database\Factories\ProfitAndLossFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property PNLStatus $status
 * @property Carbon|null $dept_head_sales_approved_at
 * @property Carbon|null $deputy_director_approved_at
 * @property Carbon|null $director_approved_at
 * @property array<string, mixed>|null $data
 * @property int|null $creator_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $created_by
 * @property-read PNLStatus $orderStatus
 */
#[ObservedBy(ProfitAndLossObserver::class)]
final class ProfitAndLoss extends Model
{
    use HasCreator;

    /** @use HasFactory<ProfitAndLossFactory> */
    use HasFactory;

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
        'status',
        'dept_head_sales_approved_at',
        'deputy_director_approved_at',
        'director_approved_at',
        'data',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'pnl_date' => 'date',
            'status' => PNLStatus::class,
            'dept_head_sales_approved_at' => 'datetime',
            'deputy_director_approved_at' => 'datetime',
            'director_approved_at' => 'datetime',
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
     * The team member who prepared the PNL.
     *
     * @return BelongsTo<User, $this>
     */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_id');
    }

    /**
     * The department head of sales team member who approved the PNL.
     *
     * @return BelongsTo<User, $this>
     */
    public function deptHeadSales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dept_head_sales_id');
    }

    /**
     * The deputy director team member who approved the PNL.
     *
     * @return BelongsTo<User, $this>
     */
    public function deputyDirector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deputy_director_id');
    }

    /**
     * The team member who approved the PNL.
     *
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /**
     * Get the computed order status based on whether the request has buyer orders.
     *
     * @return Attribute<PNLStatus, never>
     */
    protected function orderStatus(): Attribute
    {
        return Attribute::make(
            get: function (): PNLStatus {
                $hasBuyerOrders = $this->request?->buyerOrders()->exists() ?? false;

                return $hasBuyerOrders ? PNLStatus::ORDERED : PNLStatus::PENDING;
            },
        );
    }

    /**
     * Check if this PNL can be approved by the given user.
     */
    public function canBeApprovedBy(User $user): bool
    {
        // Must be in need approval status
        if (! $this->status->canApprove()) {
            return false;
        }

        // User must be in the same team
        if ($this->team_id === null || ! $user->teams->contains($this->team_id)) {
            return false;
        }

        // Check if user is assigned as one of the approvers for this PNL
        $isDeptHeadSales = $this->dept_head_sales_id === $user->id;
        $isDeputyDirector = $this->deputy_director_id === $user->id;
        $isDirector = $this->approved_by_id === $user->id;

        if (! $isDeptHeadSales && ! $isDeputyDirector && ! $isDirector) {
            return false;
        }

        // Check if user has already approved
        if ($isDeptHeadSales && $this->dept_head_sales_approved_at !== null) {
            return false;
        }
        if ($isDeputyDirector && $this->deputy_director_approved_at !== null) {
            return false;
        }
        if ($isDirector && $this->director_approved_at !== null) {
            return false;
        }

        // Verify user has the correct role
        $team = $this->team;
        if ($team === null) {
            return false;
        }

        // Administrators can approve
        if ($user->hasTeamRole($team, 'admin')) {
            return true;
        }

        // Verify role matches
        if ($isDeptHeadSales) {
            $members = TeamMemberService::getTeamMembersByCentralPurchasingRole($team, CentralPurchasingRole::DEPT_HEAD_SALES);
            if ($members->contains('id', $user->id)) {
                return true;
            }
        }
        if ($isDeputyDirector) {
            $members = TeamMemberService::getTeamMembersByCentralPurchasingRole($team, CentralPurchasingRole::DEPUTY_DIRECTOR);
            if ($members->contains('id', $user->id)) {
                return true;
            }
        }
        if ($isDirector) {
            $members = TeamMemberService::getTeamMembersByCentralPurchasingRole($team, CentralPurchasingRole::DIRECTOR);
            if ($members->contains('id', $user->id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Approve this PNL by the given user.
     */
    public function approve(User $user): void
    {
        if (! $this->canBeApprovedBy($user)) {
            throw new \InvalidArgumentException('User cannot approve this PNL.');
        }

        $now = now();

        // Mark the appropriate approver as approved
        if ($this->dept_head_sales_id === $user->id && $this->dept_head_sales_approved_at === null) {
            $this->dept_head_sales_approved_at = $now;
        }
        if ($this->deputy_director_id === $user->id && $this->deputy_director_approved_at === null) {
            $this->deputy_director_approved_at = $now;
        }
        if ($this->approved_by_id === $user->id && $this->director_approved_at === null) {
            $this->director_approved_at = $now;
        }

        // Check if all required approvers have approved
        $allApproved = true;
        if ($this->dept_head_sales_id !== null && $this->dept_head_sales_approved_at === null) {
            $allApproved = false;
        }
        if ($this->deputy_director_id !== null && $this->deputy_director_approved_at === null) {
            $allApproved = false;
        }
        if ($this->approved_by_id !== null && $this->director_approved_at === null) {
            $allApproved = false;
        }

        // Update overall status if all approvers have approved
        if ($allApproved) {
            $this->status = PNLStatus::APPROVED;
        }

        $this->save();
    }

    /**
     * Check if Dept Head Sales has approved.
     */
    public function hasDeptHeadSalesApproved(): bool
    {
        return $this->dept_head_sales_approved_at !== null;
    }

    /**
     * Check if Deputy Director has approved.
     */
    public function hasDeputyDirectorApproved(): bool
    {
        return $this->deputy_director_approved_at !== null;
    }

    /**
     * Check if Director has approved.
     */
    public function hasDirectorApproved(): bool
    {
        return $this->director_approved_at !== null;
    }
}
