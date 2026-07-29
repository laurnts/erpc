<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditLimitRequestStatus;
use App\Models\Concerns\HasTeam;
use App\Services\TeamMemberService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $team_id
 * @property int $buyer_id
 * @property string $current_limit
 * @property string $requested_limit
 * @property CreditLimitRequestStatus $status
 * @property int $requested_by_id
 * @property int|null $rejected_by_id
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property string|null $rejected_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Company $buyer
 * @property-read User $requestedBy
 * @property-read User|null $rejectedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $approvers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BuyerCreditLimitRequestApproval> $approvals
 */
final class BuyerCreditLimitRequest extends Model
{
    /** @use HasFactory<\Database\Factories\BuyerCreditLimitRequestFactory> */
    use HasFactory;

    use HasTeam;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'buyer_id',
        'current_limit',
        'requested_limit',
        'status',
        'requested_by_id',
        'rejected_by_id',
        'rejected_at',
        'rejected_reason',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => CreditLimitRequestStatus::PENDING,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'current_limit' => 'decimal:2',
            'requested_limit' => 'decimal:2',
            'status' => CreditLimitRequestStatus::class,
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function approvers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'buyer_credit_limit_request_approvals', 'buyer_credit_limit_request_id', 'user_id')
            ->withPivot(['approved_at', 'notes'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<BuyerCreditLimitRequestApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(BuyerCreditLimitRequestApproval::class);
    }

    /**
     * Get the current approval count.
     */
    public function approvalCount(): int
    {
        return $this->approvals()->count();
    }

    /**
     * Check if the request has been approved (has 2 approvals).
     */
    public function isApproved(): bool
    {
        return $this->approvalCount() >= 2;
    }

    /**
     * Check if a user can approve this request.
     */
    public function canBeApprovedBy(User $user): bool
    {
        // Must be pending
        if ($this->status !== CreditLimitRequestStatus::PENDING) {
            return false;
        }

        // User must be a finance approver
        $financeApprovers = TeamMemberService::getFinanceApprovers($this->team);

        if (! $financeApprovers->contains('id', $user->id)) {
            return false;
        }

        // User must not have already approved
        return ! $this->approvers()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if a user can reject this request.
     */
    public function canBeRejectedBy(User $user): bool
    {
        // Must be pending
        if ($this->status !== CreditLimitRequestStatus::PENDING) {
            return false;
        }

        // User must be a finance approver
        $financeApprovers = TeamMemberService::getFinanceApprovers($this->team);

        return $financeApprovers->contains('id', $user->id);
    }

    /**
     * Approve the request by a user.
     *
     * @throws \Exception
     */
    public function approve(User $user, ?string $notes = null): void
    {
        if (! $this->canBeApprovedBy($user)) {
            throw new \Exception('User cannot approve this request');
        }

        DB::transaction(function () use ($user, $notes): void {
            // Lock the request row so two concurrent second-approvals cannot both
            // observe approvalCount() >= 2 and both process the credit limit
            // change (which would double the BuyerCreditUsageHistory audit row).
            $locked = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            // Create approval record
            BuyerCreditLimitRequestApproval::create([
                'team_id' => $locked->team_id,
                'buyer_credit_limit_request_id' => $locked->id,
                'user_id' => $user->id,
                'approved_at' => now(),
                'notes' => $notes,
            ]);

            // Check if we now have 2 approvals. approvalCount() re-queries the
            // approvals relation, so this reflects the row just created above.
            if ($locked->approvalCount() >= 2) {
                // Update buyer's credit limit
                $buyer = $locked->buyer;

                // Calculate change amount. The buyer's available credit is derived
                // from credit_limit minus outstanding exposure, so it does not need
                // (and must not receive) a separate stored write here.
                $currentLimit = (float) $buyer->credit_limit;
                $requestedLimit = (float) $locked->requested_limit;
                $changeAmount = $requestedLimit - $currentLimit;
                $currentAvailableCredit = $buyer->derived_available_credit;

                // Update credit_limit to requested_limit
                $buyer->credit_limit = $locked->requested_limit;
                $buyer->requested_credit_limit = null;
                Company::withAuthorizedCreditLimitChange(function () use ($buyer): void {
                    $buyer->save();
                });

                // derived_available_credit now reflects the new credit_limit.
                $newAvailableCredit = $buyer->derived_available_credit;

                // Create credit usage history record for approved limit change
                $isIncrease = $requestedLimit >= $currentLimit;
                $absoluteChangeAmount = abs($changeAmount);

                BuyerCreditUsageHistory::create([
                    'team_id' => $buyer->team_id,
                    'buyer_id' => $buyer->id,
                    'transaction_type' => 'approved',
                    'amount' => $absoluteChangeAmount,
                    'max_credit_limit_before' => $currentLimit,
                    'max_credit_limit_after' => $requestedLimit,
                    'available_credit_before' => $currentAvailableCredit,
                    'available_credit_after' => $newAvailableCredit,
                    'credit_used_before' => 0,
                    'credit_used_after' => 0,
                    'related_type' => self::class,
                    'related_id' => $locked->id,
                    'description' => $isIncrease
                        ? 'Credit limit increased from '.number_format($currentLimit, 2).' to '.number_format($requestedLimit, 2)
                        : 'Credit limit decreased from '.number_format($currentLimit, 2).' to '.number_format($requestedLimit, 2),
                    'created_by_id' => auth()->id(),
                ]);

                // Update request status
                $locked->status = CreditLimitRequestStatus::APPROVED;
                $locked->save();
            }
        });
    }

    /**
     * Reject the request.
     */
    public function reject(User $user, string $reason): void
    {
        if (! $this->canBeRejectedBy($user)) {
            throw new \Exception('User cannot reject this request');
        }

        DB::transaction(function () use ($user, $reason): void {
            $this->status = CreditLimitRequestStatus::REJECTED;
            $this->rejected_by_id = $user->id;
            $this->rejected_at = now();
            $this->rejected_reason = $reason;
            $this->save();

            // Clear requested_credit_limit on buyer
            $buyer = $this->buyer;
            $buyer->requested_credit_limit = null;
            $buyer->save();
        });
    }
}
