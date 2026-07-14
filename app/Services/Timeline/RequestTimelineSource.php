<?php

declare(strict_types=1);

namespace App\Services\Timeline;

use App\Data\TimelineEntry;
use App\Enums\ActorType;
use App\Enums\RequestStage;
use App\Models\ActivityLog;
use App\Models\BuyerCreditUsageHistory;
use App\Models\BuyerOrder;
use App\Models\BuyerPayment;
use App\Models\Request;
use App\Models\RequestNote;
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
    /**
     * Line-item subject type => its parent header morph alias. Line rows are
     * matched to the request through the parent pointer their activity stamps
     * (App\Models\Concerns\StampsParentOnActivity), so a hard-deleted line
     * still resolves against its surviving header (design D7).
     *
     * @var array<string, string>
     */
    private const array ITEM_PARENT_ALIASES = [
        'request_item' => 'request',
        'buyer_quote_item' => 'buyer_quote',
        'supplier_quote_item' => 'supplier_quote',
        'buyer_order_item' => 'buyer_order',
        'supplier_order_item' => 'supplier_order',
        'buyer_invoice_item' => 'buyer_invoice',
        'supplier_invoice_item' => 'supplier_invoice',
        'shipment_item' => 'shipment',
    ];

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
            ->concat(in_array(TimelineAudience::ENTRY_NOTE, $entryTypes, true) ? $this->noteEntries($request) : [])
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

        $subjectType = (string) $activity->subject_type;

        // A line-item row is in-tree when its stamped parent points at one of
        // this request's headers — resilient to the line having been deleted.
        if (array_key_exists($subjectType, self::ITEM_PARENT_ALIASES)) {
            $parentNumbers = $this->subjectNumbersFor($request, self::ITEM_PARENT_ALIASES[$subjectType]);
            $parentId = (int) $activity->properties->get('parent_id');

            return array_key_exists($parentId, $parentNumbers);
        }

        $numbers = $this->subjectNumbers($request, $party)[$subjectType] ?? [];

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
        // Line-item rows are surfaced by matching their stamped parent pointer
        // (see itemActivityEntries), so their ids are not enumerated here; the
        // key is still present for the allow-list completeness guard.
        if (array_key_exists($subjectType, self::ITEM_PARENT_ALIASES)) {
            return [];
        }

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
        return $this->headerActivityEntries($subjectNumbers)
            ->concat($this->itemActivityEntries($subjectNumbers));
    }

    /**
     * Header/document activity rows via the (subject_type, subject_id) tuples.
     * Item subject types enumerate to empty (design D7) and drop out here; they
     * are handled by itemActivityEntries.
     *
     * @param  array<string, array<int, string|null>>  $subjectNumbers
     * @return Collection<int, TimelineEntry>
     */
    private function headerActivityEntries(array $subjectNumbers): Collection
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
                    headline: $this->activityHeadline($event, $subjectType, $subjectNumber, $subjectId, $attributes),
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
     * Approval timestamp/approver columns → the human role that approved.
     * The activity causer is *who* approved; the field tells *which* role.
     *
     * @var array<string, string>
     */
    private const array APPROVAL_FIELD_LABELS = [
        'dept_head_sales_approved_at' => 'Dept Head (Sales)',
        'deputy_director_approved_at' => 'Deputy Director',
        'director_approved_at' => 'Director',
        'approver_1_id' => 'Approver 1',
        'approver_2_id' => 'Approver 2',
        'approved_at' => 'final approval',
    ];

    /**
     * Render an atomic, human headline: a stage change reads as workflow
     * progress, and setting an approval field reads as that role's approval
     * (attributed to the causer), instead of a generic "updated · 1 field".
     *
     * @param  array<string, mixed>  $attributes
     */
    private function activityHeadline(string $event, string $subjectType, ?string $subjectNumber, int $subjectId, array $attributes): string
    {
        $label = $subjectNumber ?? '#'.$subjectId;

        if ($subjectType === 'request' && array_key_exists('stage', $attributes) && $event !== 'created') {
            $stage = $attributes['stage'];
            $stageLabel = $stage instanceof RequestStage
                ? $stage->getLabel()
                : (RequestStage::tryFrom((string) $stage)?->getLabel() ?? (string) $stage);

            return 'Progressed to '.$stageLabel;
        }

        foreach (self::APPROVAL_FIELD_LABELS as $field => $roleLabel) {
            if (array_key_exists($field, $attributes) && $attributes[$field] !== null) {
                return sprintf('Approved %s %s — %s', Str::headline($subjectType), $label, $roleLabel);
            }
        }

        return trim(sprintf('%s %s %s', $event, Str::headline($subjectType), $label));
    }

    /**
     * Line-item activity rows, matched to the request through the parent
     * pointer each item stamps into properties, then grouped under (and
     * numbered by) their parent header. Matching by parent — not by the line's
     * own id — keeps a hard-deleted line's deletion snapshot visible.
     *
     * @param  array<string, array<int, string|null>>  $subjectNumbers
     * @return Collection<int, TimelineEntry>
     */
    private function itemActivityEntries(array $subjectNumbers): Collection
    {
        /** @var array<string, array<int, string|null>> $parentNumbersByAlias */
        $parentNumbersByAlias = [];

        foreach (self::ITEM_PARENT_ALIASES as $parentAlias) {
            $parentNumbersByAlias[$parentAlias] = $subjectNumbers[$parentAlias] ?? [];
        }

        $itemTypesWithParents = array_filter(
            self::ITEM_PARENT_ALIASES,
            fn (string $parentAlias): bool => ($parentNumbersByAlias[$parentAlias] ?? []) !== [],
        );

        if ($itemTypesWithParents === []) {
            return collect();
        }

        return ActivityLog::query()
            ->with('causer')
            ->where(function (Builder $query) use ($itemTypesWithParents, $parentNumbersByAlias): void {
                foreach ($itemTypesWithParents as $itemType => $parentAlias) {
                    $query->orWhere(function (Builder $tuple) use ($itemType, $parentAlias, $parentNumbersByAlias): void {
                        $tuple->where('subject_type', $itemType)
                            ->where('properties->parent_type', $parentAlias)
                            ->whereIn('properties->parent_id', array_keys($parentNumbersByAlias[$parentAlias]));
                    });
                }
            })
            ->get()
            ->map(function (ActivityLog $activity) use ($parentNumbersByAlias): TimelineEntry {
                $parentAlias = self::ITEM_PARENT_ALIASES[(string) $activity->subject_type];

                return $this->itemEntry($activity, $parentAlias, $parentNumbersByAlias[$parentAlias]);
            });
    }

    /**
     * @param  array<int, string|null>  $parentNumbers
     */
    private function itemEntry(ActivityLog $activity, string $parentAlias, array $parentNumbers): TimelineEntry
    {
        $properties = $activity->properties;
        $parentId = (int) $properties->get('parent_id');
        $parentNumber = $parentNumbers[$parentId] ?? null;
        $lineLabel = (string) ($properties->get('line_label') ?? 'line');
        $attributes = (array) $properties->get('attributes', []);
        $old = (array) $properties->get('old', []);
        $labels = (array) $properties->get('labels', []);
        $event = $activity->event ?? 'logged';
        $changedFields = $event === 'deleted' ? $old : $attributes;

        return new TimelineEntry(
            actorLabel: $activity->causer?->getAttribute('name') ?? 'System',
            actorType: $activity->actor_type ?? ActorType::System,
            entryType: TimelineAudience::ENTRY_ACTIVITY,
            event: $event,
            headline: $this->itemHeadline($event, $parentAlias, $parentNumber, $lineLabel, $attributes, $old, $labels),
            subjectType: (string) $activity->subject_type,
            subjectId: (int) $activity->subject_id,
            subjectNumber: $parentNumber,
            changedFieldCount: count($changedFields),
            occurredAt: $activity->created_at->toImmutable(),
            properties: [
                'activity_id' => (int) $activity->getKey(),
                'attributes' => $attributes,
                'old' => $old,
                'parent_type' => $parentAlias,
                'parent_id' => $parentId,
                'line_label' => $lineLabel,
                'labels' => $labels,
            ],
        );
    }

    /**
     * "Buyer Quote BQ-123 — line "Steel pipe" unit_price 100 → 50", with the
     * field changes (FK ids already resolved to labels) inlined for updates and
     * a plain added/removed verb for creations and deletions.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $old
     * @param  array<string, array{old?: string|null, new?: string|null}>  $labels
     */
    private function itemHeadline(
        string $event,
        string $parentAlias,
        ?string $parentNumber,
        string $lineLabel,
        array $attributes,
        array $old,
        array $labels,
    ): string {
        $context = trim(sprintf(
            '%s %s — line "%s"',
            Str::headline($parentAlias),
            $parentNumber ?? '',
            $lineLabel,
        ));
        $context = (string) preg_replace('/\s{2,}/', ' ', $context);

        if ($event === 'created') {
            return $context.' added';
        }

        if ($event === 'deleted') {
            return $context.' removed';
        }

        $changes = $this->formatFieldChanges($attributes, $old, $labels);

        return $changes === '' ? $context.' updated' : $context.' '.$changes;
    }

    /**
     * Compact "field old → new" summary (first three fields), FK fields shown
     * through their resolved labels.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $old
     * @param  array<string, array{old?: string|null, new?: string|null}>  $labels
     */
    private function formatFieldChanges(array $attributes, array $old, array $labels): string
    {
        $parts = [];

        foreach ($attributes as $field => $newValue) {
            if (array_key_exists($field, $labels)) {
                $oldLabel = $labels[$field]['old'] ?? $this->scalar($old[$field] ?? null);
                $newLabel = $labels[$field]['new'] ?? $this->scalar($newValue);
                $parts[] = sprintf('%s %s → %s', $field, $oldLabel ?? '—', $newLabel ?? '—');

                continue;
            }

            $parts[] = sprintf(
                '%s %s → %s',
                $field,
                $this->scalar($old[$field] ?? null),
                $this->scalar($newValue),
            );
        }

        if ($parts === []) {
            return '';
        }

        return implode(', ', array_slice($parts, 0, 3)).(count($parts) > 3 ? ', …' : '');
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        return (string) $value;
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
            // System is reserved for genuine automation (scheduled jobs,
            // outbound emails/reminders) and for legacy uploads that predate
            // uploader stamping and have not yet been run through
            // `timeline:backfill-attribution`. A stamped upload always carries
            // its real actor, so a person-driven history should not read as
            // System once the backfill has run.
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
                url: route('documents.download', ['media' => $item, 'download' => 1]),
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

        // Format money through the team's base currency (the app-wide standard,
        // e.g. IDR renders "Rp 11.200.000,-") rather than a raw number_format.
        $currency = $request->team?->getBaseCurrency();
        $money = fn (float|int|string $value): string => $currency !== null
            ? $currency->format((float) $value)
            : number_format((float) $value, 2);

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
            ->map(function (BuyerCreditUsageHistory $row) use ($orderNumbers, $money): TimelineEntry {
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
                    headline: $this->creditHeadline($row, $money),
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

    /**
     * @param  callable(float|int|string): string  $money
     */
    private function creditHeadline(BuyerCreditUsageHistory $row, callable $money): string
    {
        $limitBefore = (float) $row->max_credit_limit_before;
        $limitAfter = (float) $row->max_credit_limit_after;

        if ($row->transaction_type === 'approved' || $limitBefore !== $limitAfter) {
            return sprintf('Credit limit %s → %s', $money($limitBefore), $money($limitAfter));
        }

        // Derive the verb from the balance direction rather than a specific
        // transaction_type literal (writers use 'debit'/'credit' and a
        // dynamic value); rising credit_used means credit was consumed.
        $verb = (float) $row->credit_used_after >= (float) $row->credit_used_before
            ? 'Credit used'
            : 'Credit released';

        return sprintf(
            '%s %s — available %s → %s',
            $verb,
            $money($row->amount),
            $money($row->available_credit_before),
            $money($row->available_credit_after),
        );
    }

    /**
     * Note lane (design D2): every note pinned to the request, newest handled
     * by the shared sort. Staff/admin see all notes regardless of visibility,
     * with the author's real name attributed.
     *
     * @return Collection<int, TimelineEntry>
     */
    private function noteEntries(Request $request): Collection
    {
        return RequestNote::query()
            ->with(['author', 'media'])
            ->where('request_id', $request->getKey())
            ->get()
            ->map(function (RequestNote $note) use ($request): TimelineEntry {
                $attachments = $note->getMedia(RequestNote::ATTACHMENTS_COLLECTION)
                    ->map(fn (Media $media): string => (string) $media->file_name)
                    ->values()
                    ->all();

                return new TimelineEntry(
                    actorLabel: $note->author?->getAttribute('name') ?? $note->author_actor_type->getLabel(),
                    actorType: $note->author_actor_type,
                    entryType: TimelineAudience::ENTRY_NOTE,
                    event: 'note',
                    headline: $this->noteHeadline($note->body, $attachments),
                    subjectType: 'request',
                    subjectId: (int) $note->request_id,
                    subjectNumber: $request->request_number,
                    changedFieldCount: 0,
                    occurredAt: $note->created_at->toImmutable(),
                    properties: ['visibility' => $note->visibility->value],
                    lane: 'note',
                    body: ($note->body === null || $note->body === '') ? null : $note->body,
                    attachments: $attachments,
                );
            });
    }

    /**
     * Build a note's rendered headline from its body and attachment names so
     * both surface through views that render only the headline. An
     * attachment-only note falls back to describing its files.
     *
     * @param  list<string>  $attachments
     */
    private function noteHeadline(?string $body, array $attachments): string
    {
        $body = trim((string) $body);

        if ($body === '') {
            return $attachments === []
                ? 'Note'
                : 'Note attachment: '.implode(', ', $attachments);
        }

        $headline = 'Note: '.$body;

        if ($attachments !== []) {
            $headline .= ' (attachment: '.implode(', ', $attachments).')';
        }

        return $headline;
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
