<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CentralPurchasingRole;
use App\Enums\QEStatus;
use App\Enums\SupplierQuoteStatus;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\QuotationEvaluationObserver;
use App\Services\TeamMemberService;
use App\Support\RomanNumerals;
use Database\Factories\QuotationEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
 * @property QEStatus $status
 * @property Carbon|null $dept_head_sales_approved_at
 * @property Carbon|null $deputy_director_approved_at
 * @property Carbon|null $director_approved_at
 * @property array<string, mixed> $data
 * @property int|null $creator_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $created_by
 */
#[ObservedBy(QuotationEvaluationObserver::class)]
final class QuotationEvaluation extends Model implements HasMedia
{
    use HasCreator;

    /** @use HasFactory<QuotationEvaluationFactory> */
    use HasFactory;

    use HasTeam;
    use InteractsWithMedia;

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
            'qe_date' => 'date',
            'status' => QEStatus::class,
            'dept_head_sales_approved_at' => 'datetime',
            'deputy_director_approved_at' => 'datetime',
            'director_approved_at' => 'datetime',
            'data' => 'array',
        ];
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')
            ->useDisk('local');
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
     * The team member who prepared the QE.
     *
     * @return BelongsTo<User, $this>
     */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_id');
    }

    /**
     * The department head of sales team member who approved the QE.
     *
     * @return BelongsTo<User, $this>
     */
    public function deptHeadSales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dept_head_sales_id');
    }

    /**
     * The deputy director team member who approved the QE.
     *
     * @return BelongsTo<User, $this>
     */
    public function deputyDirector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deputy_director_id');
    }

    /**
     * The team member who approved the QE.
     *
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
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

    /**
     * Check if a user can approve this QE.
     *
     * @return bool True if user can approve
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

        // Check if user is assigned as one of the approvers for this QE
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
     * Approve this QE by the given user.
     */
    public function approve(User $user): void
    {
        if (! $this->canBeApprovedBy($user)) {
            throw new \InvalidArgumentException('User cannot approve this QE.');
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

        // If all approvers have approved, mark QE as approved
        if ($allApproved) {
            $this->status = QEStatus::APPROVED;
        }

        $this->save();
    }

    /**
     * Mark this QE as approved via document acceptance (key account approved the document in Acceptance Report).
     */
    public function approveViaDocumentAcceptance(User $user): void
    {
        $now = now();
        $this->dept_head_sales_approved_at = $this->dept_head_sales_approved_at ?? $now;
        $this->deputy_director_approved_at = $this->deputy_director_approved_at ?? $now;
        $this->director_approved_at = $this->director_approved_at ?? $now;
        $this->status = QEStatus::APPROVED;
        $this->save();
    }

    /**
     * Check if Dept Head of Sales has approved.
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

    /**
     * Get the current approval count (number of approvers who have approved).
     */
    public function approvalCount(): int
    {
        $count = 0;

        if ($this->dept_head_sales_id !== null && $this->dept_head_sales_approved_at !== null) {
            $count++;
        }
        if ($this->deputy_director_id !== null && $this->deputy_director_approved_at !== null) {
            $count++;
        }
        if ($this->approved_by_id !== null && $this->director_approved_at !== null) {
            $count++;
        }

        return $count;
    }

    /**
     * Get the total number of required approvers.
     */
    public function totalApproversCount(): int
    {
        $count = 0;

        if ($this->dept_head_sales_id !== null) {
            $count++;
        }
        if ($this->deputy_director_id !== null) {
            $count++;
        }
        if ($this->approved_by_id !== null) {
            $count++;
        }

        return $count;
    }

    /**
     * Get collection of approvers who have approved.
     *
     * @return Collection<int, User>
     */
    public function getApprovers(): Collection
    {
        $approvers = collect();

        if ($this->dept_head_sales_id !== null && $this->dept_head_sales_approved_at !== null && $this->deptHeadSales) {
            $approvers->push($this->deptHeadSales);
        }
        if ($this->deputy_director_id !== null && $this->deputy_director_approved_at !== null && $this->deputyDirector) {
            $approvers->push($this->deputyDirector);
        }
        if ($this->approved_by_id !== null && $this->director_approved_at !== null && $this->approvedBy) {
            $approvers->push($this->approvedBy);
        }

        return $approvers;
    }

    /**
     * Sync snapshot data from supplier quotes.
     * If QE is approved, resets status to NEED_APPROVAL and clears approval timestamps
     * to restart the approval workflow when new quotes/items are added.
     *
     * @return bool True if data was synced, false otherwise
     */
    public function syncSnapshotData(): bool
    {
        // Ensure request relationship is loaded
        if ($this->request === null) {
            return false;
        }

        // If QE is approved, reset to pending and clear approval timestamps
        // This allows the QE to be updated when new quotes/items are added
        if ($this->status === QEStatus::APPROVED) {
            $this->status = QEStatus::NEED_APPROVAL;
            $this->dept_head_sales_approved_at = null;
            $this->deputy_director_approved_at = null;
            $this->director_approved_at = null;
        }

        // Get all active quotes
        $quotes = $this->request->supplierQuotes()
            ->whereIn('status', [SupplierQuoteStatus::RECEIVED, SupplierQuoteStatus::SELECTED])
            ->with(['supplier', 'currency', 'items.requestItem'])
            ->orderBy('total_base')
            ->get();

        // Get request items
        $requestItems = $this->request->items()->with('article')->orderBy('sort_order')->get();

        // Build price matrix and find best prices
        $bestPrices = $this->findBestPrices($requestItems, $quotes);

        // Build items array
        $items = [];
        foreach ($requestItems as $requestItem) {
            $itemData = [
                'id' => $requestItem->getKey(),
                'description' => $requestItem->article?->name ?? $requestItem->description,
                'quantity' => (float) $requestItem->quantity,
                'unit' => $requestItem->unit ?? 'pcs',
                'prices' => [],
            ];

            foreach ($quotes as $quote) {
                $quoteItem = $quote->items->first(
                    fn (\App\Models\SupplierQuoteItem $item): bool => $item->request_item_id === $requestItem->getKey()
                );

                if ($quoteItem !== null) {
                    $isBestPrice = ($bestPrices[$requestItem->getKey()] ?? null) === $quote->getKey();

                    $itemData['prices'][(string) $quote->getKey()] = [
                        'supplier_id' => $quote->supplier_id,
                        'unit_price' => (float) $quoteItem->unit_price_exc_tax,
                        'line_subtotal' => (float) $quoteItem->line_subtotal,
                        'line_tax' => (float) $quoteItem->line_tax,
                        'line_total' => (float) $quoteItem->line_total,
                        'is_best_price' => $isBestPrice,
                        'is_selected' => $quoteItem->is_selected,
                    ];
                }
            }

            $items[] = $itemData;
        }

        // Build suppliers array
        $suppliers = [];
        foreach ($quotes as $quote) {
            $suppliers[] = [
                'id' => $quote->getKey(),
                'name' => $quote->supplier?->name ?? 'Unknown',
                'currency_code' => $quote->currency?->code ?? 'USD',
                'delivery_type' => $quote->supplier?->delivery_type ?? null,
                'delivery_type_details' => $quote->supplier?->delivery_type_details ?? null,
                'is_taxable' => $quote->supplier?->is_taxable ?? false,
                'delivery_term' => $quote->supplier?->delivery_term ?? null,
                'payment_terms_days' => $quote->supplier?->payment_terms ?? null,
                'subtotal' => (float) $quote->subtotal,
                'tax_total' => (float) $quote->tax_total,
                'grand_total' => (float) $quote->total,
            ];
        }

        // Update the data field
        $this->data = [
            'request' => [
                'id' => $this->request->getKey(),
                'request_number' => $this->request->request_number,
                'title' => $this->request->title,
            ],
            'items' => $items,
            'suppliers' => $suppliers,
        ];

        $this->saveQuietly();

        return true;
    }

    /**
     * Find the best price (lowest unit price in base currency) for each request item.
     *
     * @param  Collection<int, \App\Models\RequestItem>  $requestItems
     * @param  Collection<int, \App\Models\SupplierQuote>  $quotes
     * @return array<int, int|null>
     */
    private function findBestPrices(Collection $requestItems, Collection $quotes): array
    {
        $bestPrices = [];

        foreach ($requestItems as $requestItem) {
            $bestQuoteId = null;
            $bestUnitPriceBase = null;

            foreach ($quotes as $quote) {
                $quoteItem = $quote->items->first(
                    fn (\App\Models\SupplierQuoteItem $item): bool => $item->request_item_id === $requestItem->getKey()
                );

                if ($quoteItem === null) {
                    continue;
                }

                // Compare unit price in base currency
                $unitPriceBase = (float) $quoteItem->unit_price_exc_tax * (float) $quote->exchange_rate;

                if ($bestUnitPriceBase === null || $unitPriceBase < $bestUnitPriceBase) {
                    $bestUnitPriceBase = $unitPriceBase;
                    $bestQuoteId = $quote->getKey();
                }
            }

            $bestPrices[$requestItem->getKey()] = $bestQuoteId;
        }

        return $bestPrices;
    }
}
