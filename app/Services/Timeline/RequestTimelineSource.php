<?php

declare(strict_types=1);

namespace App\Services\Timeline;

use App\Data\TimelineEntry;
use App\Enums\ActorType;
use App\Models\ActivityLog;
use App\Models\BuyerCreditUsageHistory;
use App\Models\BuyerOrder;
use App\Models\BuyerPayment;
use App\Models\Request;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Internal (staff/admin) per-request timeline read source (design D1).
 *
 * Resolves the request's logged child tree through the audience helper's
 * staff allow-list (interim subject enumeration, design D7), then merges
 * three lanes into one chronological feed of TimelineEntry rows:
 *
 *  - activity: ONE whereIn query over (subject_type, subject_id) tuples on
 *    the live activity_log, causer eager-loaded;
 *  - media: uploads across the request's own collections and its child
 *    documents, attributed from the attach-time uploader stamp (unstamped
 *    rows render as System/Unknown);
 *  - credit: BuyerCreditUsageHistory ledger rows caused by the request's
 *    buyer orders, plus approved limit changes for the request's buyer,
 *    rendered with amount and before→after balances (design D8).
 *
 * Buyer/supplier surfaces must NOT consume this source — they get their own
 * hard-scoped additive sources (design D2); this one is internal-only.
 */
final readonly class RequestTimelineSource
{
    public function __construct(private TimelineAudience $audience) {}

    /**
     * The merged, newest-first timeline page for an internal party.
     *
     * @return LengthAwarePaginator<int, TimelineEntry>
     */
    public function entries(Request $request, TimelineParty $party, int $page = 1, int $perPage = 25): LengthAwarePaginator
    {
        $this->guardInternal($party);

        $subjectNumbers = $this->subjectNumbers($request, $party);
        $entryTypes = $this->audience->entryTypes($party);

        /** @var Collection<int, TimelineEntry> $entries */
        $entries = collect()
            ->concat(in_array(TimelineAudience::ENTRY_ACTIVITY, $entryTypes, true) ? $this->activityEntries($subjectNumbers) : [])
            ->concat(in_array(TimelineAudience::ENTRY_MEDIA, $entryTypes, true) ? $this->mediaEntries($subjectNumbers) : [])
            ->concat(in_array(TimelineAudience::ENTRY_CREDIT, $entryTypes, true) ? $this->creditEntries($request, $subjectNumbers) : [])
            ->sortByDesc(fn (TimelineEntry $entry): string => $entry->occurredAt->format('Y-m-d H:i:s.u'))
            ->values();

        $page = max(1, $page);

        return new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->values(),
            $entries->count(),
            $perPage,
            $page,
        );
    }

    /**
     * Whether an activity row belongs to this request's subject tree for the
     * party — the guard the detail modal uses against tampered activity ids.
     */
    public function allowsActivity(Request $request, TimelineParty $party, ActivityLog $activity): bool
    {
        $this->guardInternal($party);

        if ($activity->subject_type === null || $activity->subject_id === null) {
            return false;
        }

        $numbers = $this->subjectNumbers($request, $party)[$activity->subject_type] ?? [];

        return array_key_exists((int) $activity->subject_id, $numbers);
    }

    /**
     * The request's subject id => display-number map per allow-listed subject
     * type. Driven by the audience helper so a subject type the party may not
     * see is never enumerated, and public so the completeness guard test can
     * assert every allow-listed type has an enumeration path.
     *
     * @return array<string, array<int, string|null>>
     */
    public function subjectNumbers(Request $request, TimelineParty $party): array
    {
        $numbers = [];

        foreach (array_keys($this->audience->subjectRules($party)) as $subjectType) {
            $numbers[$subjectType] = $this->subjectNumbersFor($request, $subjectType);
        }

        return $numbers;
    }

    /**
     * Interim per-type enumeration (design D7): swaps to the line-item
     * change's parent_type/parent_id predicate once that lands. Soft-deleted
     * children stay enumerable so their deletion history keeps rendering.
     *
     * @return array<int, string|null> subject id => display document number
     */
    private function subjectNumbersFor(Request $request, string $subjectType): array
    {
        return match ($subjectType) {
            'request' => [(int) $request->getKey() => $request->request_number],
            'buyer_quote' => $request->buyerQuotes()->withTrashed()->pluck('quote_number', 'id')->all(),
            'supplier_quote' => $request->supplierQuotes()->withTrashed()->pluck('quote_number', 'id')->all(),
            'buyer_order' => $request->buyerOrders()->withTrashed()->pluck('order_number', 'id')->all(),
            'supplier_order' => $request->supplierOrders()->withTrashed()->pluck('po_number', 'id')->all(),
            'buyer_invoice' => $request->buyerInvoices()->withTrashed()->pluck('invoice_number', 'id')->all(),
            'supplier_invoice' => $request->supplierInvoices()->withTrashed()->pluck('invoice_number', 'id')->all(),
            'buyer_payment' => BuyerPayment::withTrashed()
                ->whereIn('buyer_invoice_id', $request->buyerInvoices()->withTrashed()->select('id'))
                ->pluck('payment_number', 'id')
                ->all(),
            'supplier_payment' => SupplierPayment::withTrashed()
                ->whereIn('supplier_invoice_id', $request->supplierInvoices()->withTrashed()->select('id'))
                ->pluck('payment_number', 'id')
                ->all(),
            'shipment' => $request->shipments()->withTrashed()->pluck('shipment_number', 'id')->all(),
            'quotation_evaluation' => $request->quotationEvaluations()->pluck('qe_number', 'id')->all(),
            'profit_and_loss' => $request->profitAndLosses()->pluck('pnl_number', 'id')->all(),
            'acceptance_report' => $request->acceptanceReports()->withTrashed()->pluck('report_number', 'id')->all(),
            'goods_receive_batch' => array_fill_keys($request->goodsReceiveBatches()->pluck('id')->all(), null),
            default => throw new RuntimeException(
                "No subject enumeration for '{$subjectType}' — add it to RequestTimelineSource and ensure the model has a capture path."
            ),
        };
    }

    /**
     * @param  array<string, array<int, string|null>>  $subjectNumbers
     * @return Collection<int, TimelineEntry>
     */
    private function activityEntries(array $subjectNumbers): Collection
    {
        $subjectNumbers = array_filter($subjectNumbers, fn (array $numbers): bool => $numbers !== []);

        if ($subjectNumbers === []) {
            return collect();
        }

        return ActivityLog::query()
            ->with('causer')
            ->where(function (Builder $query) use ($subjectNumbers): void {
                foreach ($subjectNumbers as $subjectType => $numbers) {
                    $query->orWhere(function (Builder $tuple) use ($subjectType, $numbers): void {
                        $tuple->where('subject_type', $subjectType)
                            ->whereIn('subject_id', array_keys($numbers));
                    });
                }
            })
            ->get()
            ->map(function (ActivityLog $activity) use ($subjectNumbers): TimelineEntry {
                $subjectType = (string) $activity->subject_type;
                $subjectId = (int) $activity->subject_id;
                $subjectNumber = $subjectNumbers[$subjectType][$subjectId] ?? null;
                $attributes = (array) $activity->properties->get('attributes', []);
                $event = $activity->event ?? 'logged';

                return new TimelineEntry(
                    actorLabel: $activity->causer?->getAttribute('name') ?? 'System',
                    actorType: $activity->actor_type ?? ActorType::System,
                    entryType: TimelineAudience::ENTRY_ACTIVITY,
                    event: $event,
                    headline: trim(sprintf(
                        '%s %s %s',
                        $event,
                        Str::headline($subjectType),
                        $subjectNumber ?? '#'.$subjectId,
                    )),
                    subjectType: $subjectType,
                    subjectId: $subjectId,
                    subjectNumber: $subjectNumber,
                    changedFieldCount: count($attributes),
                    occurredAt: $activity->created_at->toImmutable(),
                    properties: [
                        'activity_id' => (int) $activity->getKey(),
                        'attributes' => $attributes,
                        'old' => (array) $activity->properties->get('old', []),
                    ],
                );
            });
    }

    /**
     * Uploads on the request and its child documents, attributed from the
     * attach-time uploader stamp; unstamped media renders as System/Unknown
     * for internal parties (staff media rules allow unstamped, fail-open).
     *
     * @param  array<string, array<int, string|null>>  $subjectNumbers
     * @return Collection<int, TimelineEntry>
     */
    private function mediaEntries(array $subjectNumbers): Collection
    {
        $subjectNumbers = array_filter($subjectNumbers, fn (array $numbers): bool => $numbers !== []);

        if ($subjectNumbers === []) {
            return collect();
        }

        $media = Media::query()
            ->where(function (Builder $query) use ($subjectNumbers): void {
                foreach ($subjectNumbers as $subjectType => $numbers) {
                    $query->orWhere(function (Builder $tuple) use ($subjectType, $numbers): void {
                        $tuple->where('model_type', $subjectType)
                            ->whereIn('model_id', array_keys($numbers));
                    });
                }
            })
            ->get();

        $uploaderNames = User::query()
            ->whereIn('id', $media->map(fn (Media $item): mixed => $item->getCustomProperty('uploader_id'))->filter()->unique())
            ->pluck('name', 'id');

        return $media->map(function (Media $item) use ($subjectNumbers, $uploaderNames): TimelineEntry {
            $actorType = ActorType::tryFrom((string) $item->getCustomProperty('uploader_actor_type')) ?? ActorType::System;
            $uploaderId = $item->getCustomProperty('uploader_id');
            $actorLabel = $uploaderId !== null
                ? ($uploaderNames->get((int) $uploaderId) ?? 'Unknown')
                : ($actorType === ActorType::System ? 'System' : 'Unknown');

            return new TimelineEntry(
                actorLabel: $actorLabel,
                actorType: $actorType,
                entryType: TimelineAudience::ENTRY_MEDIA,
                event: 'uploaded',
                headline: sprintf('uploaded %s → %s', $item->file_name, Str::headline($item->collection_name)),
                subjectType: (string) $item->model_type,
                subjectId: (int) $item->model_id,
                subjectNumber: $subjectNumbers[(string) $item->model_type][(int) $item->model_id] ?? null,
                changedFieldCount: 0,
                occurredAt: $item->created_at->toImmutable(),
            );
        });
    }

    /**
     * Credit-ledger lane (design D8): read-only over BuyerCreditUsageHistory,
     * scoped to rows caused by this request's buyer orders plus approved
     * limit changes for the request's buyer. Ledger facts (balances) stay in
     * the ledger; this lane only renders them.
     *
     * @param  array<string, array<int, string|null>>  $subjectNumbers
     * @return Collection<int, TimelineEntry>
     */
    private function creditEntries(Request $request, array $subjectNumbers): Collection
    {
        $orderNumbers = $subjectNumbers['buyer_order'] ?? [];

        return BuyerCreditUsageHistory::query()
            ->with('createdBy')
            ->where(function (Builder $query) use ($orderNumbers, $request): void {
                if ($orderNumbers !== []) {
                    $query->orWhere(function (Builder $caused) use ($orderNumbers): void {
                        // Writers stamp related_type with the FQCN; tolerate the
                        // morph alias too so an alias-stamped row still matches.
                        $caused->whereIn('related_type', [BuyerOrder::class, 'buyer_order'])
                            ->whereIn('related_id', array_keys($orderNumbers));
                    });
                }

                $query->orWhere(function (Builder $limitChanges) use ($request): void {
                    $limitChanges->where('buyer_id', $request->buyer_id)
                        ->where('transaction_type', 'approved');
                });
            })
            ->get()
            ->map(function (BuyerCreditUsageHistory $row) use ($orderNumbers): TimelineEntry {
                $causedByOrder = in_array($row->related_type, [BuyerOrder::class, 'buyer_order'], true)
                    ? ($orderNumbers[(int) $row->related_id] ?? null)
                    : null;

                /** @var User|null $recordedBy */
                $recordedBy = $row->createdBy;

                return new TimelineEntry(
                    actorLabel: $recordedBy->name ?? 'System',
                    actorType: $recordedBy !== null ? ActorType::Staff : ActorType::System,
                    entryType: TimelineAudience::ENTRY_CREDIT,
                    event: $row->transaction_type,
                    headline: $this->creditHeadline($row),
                    subjectType: 'buyer_credit_usage_history',
                    subjectId: (int) $row->getKey(),
                    subjectNumber: $causedByOrder,
                    changedFieldCount: 0,
                    occurredAt: $row->created_at->toImmutable(),
                    properties: [
                        'description' => $row->description,
                        'available_credit_before' => $row->available_credit_before,
                        'available_credit_after' => $row->available_credit_after,
                        'caused_by' => $causedByOrder,
                        'recorded_by' => $recordedBy?->name,
                    ],
                    lane: 'credit',
                );
            });
    }

    private function creditHeadline(BuyerCreditUsageHistory $row): string
    {
        if ($row->transaction_type === 'approved') {
            return sprintf(
                'Credit limit %s → %s',
                number_format((float) $row->max_credit_limit_before, 2),
                number_format((float) $row->max_credit_limit_after, 2),
            );
        }

        $verb = $row->transaction_type === 'debit' ? 'Credit used' : 'Credit released';

        return sprintf(
            '%s %s — available %s → %s',
            $verb,
            number_format((float) $row->amount, 2),
            number_format((float) $row->available_credit_before, 2),
            number_format((float) $row->available_credit_after, 2),
        );
    }

    private function guardInternal(TimelineParty $party): void
    {
        if (! $party->isInternal()) {
            throw new InvalidArgumentException(
                'RequestTimelineSource serves internal parties only; portal surfaces use their own hard-scoped sources.'
            );
        }
    }
}
