<?php

declare(strict_types=1);

namespace App\Services\Timeline;

use App\Data\TimelineEntry;
use App\Enums\ActorType;
use App\Enums\RequestStage;
use App\Models\ActivityLog;
use App\Models\BuyerPayment;
use App\Models\Request;
use App\Models\RequestNote;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Portal (buyer/supplier) per-request timeline read source (design D2).
 *
 * This is the hard-scoped ADDITIVE counterpart to RequestTimelineSource:
 * portals MUST NOT consume the internal source and filter it down. Instead
 * this source builds the feed from the party's positive allow-list only —
 * for each subject rule the audience helper grants, it SELECTS just that
 * subject type, scoped to the request AND the rule's identity constraints
 * (buyer_id / supplier_id / relationship predicates). A positive allow-list
 * cannot leak what it never selects, so supplier cost, margin, and the
 * credit-ledger lane (internal-only per entryTypes) are absent by construction.
 *
 * Media is fail-closed: only collections with a rule are considered, and a
 * row must carry an uploader stamp matching the rule (unstamped media is
 * dropped for portal parties). Residual presentation concerns — a staff
 * causer name, a raw stage value, an internal link — are removed by the
 * party's RedactionRules before an entry ever reaches the view.
 */
final readonly class PortalTimelineSource
{
    public function __construct(private TimelineAudience $audience) {}

    /**
     * The redacted, newest-first timeline for a portal (buyer/supplier) party.
     *
     * @return list<TimelineEntry>
     */
    public function forParty(Request $request, TimelineParty $party): array
    {
        $this->guardPortal($party);

        $subjects = $this->allowedSubjects($request, $party);
        $entryTypes = $this->audience->entryTypes($party);
        $redaction = $this->audience->redactionRules($party);
        $disallowedActors = $this->disallowedActorTypes($party);

        /** @var Collection<int, TimelineEntry> $entries */
        $entries = collect()
            ->concat(in_array(TimelineAudience::ENTRY_ACTIVITY, $entryTypes, true) ? $this->activityEntries($subjects) : [])
            ->concat(in_array(TimelineAudience::ENTRY_MEDIA, $entryTypes, true) ? $this->mediaEntries($subjects, $party) : [])
            ->concat(in_array(TimelineAudience::ENTRY_NOTE, $entryTypes, true) ? $this->noteEntries($request, $party) : [])
            ->reject(fn (TimelineEntry $entry): bool => in_array($entry->actorType, $disallowedActors, true))
            ->map(fn (TimelineEntry $entry): TimelineEntry => $this->redact($entry, $party, $redaction))
            ->sortByDesc(fn (TimelineEntry $entry): string => $entry->occurredAt->format('Y-m-d H:i:s.u'))
            ->values();

        return array_values($entries->all());
    }

    /**
     * Resolve the party's visible subject id => display-number map per
     * allow-listed subject type. Each type is queried on its own, scoped to
     * the request AND its rule's identity constraints, so a subject a party
     * may not see is never enumerated and another company's rows on a shared
     * request are excluded at the query level (identity isolation).
     *
     * @return array<string, array<int, string|null>>
     */
    private function allowedSubjects(Request $request, TimelineParty $party): array
    {
        $subjects = [];

        foreach ($this->audience->subjectRules($party) as $subjectType => $rule) {
            [$query, $numberColumn] = $this->baseSubjectQuery($request, $subjectType);

            $this->applyRule($query, $rule);

            /** @var array<int, string|null> $numbers */
            $numbers = $query->pluck($numberColumn, 'id')->all();

            $subjects[$subjectType] = $numbers;
        }

        return $subjects;
    }

    /**
     * The request-scoped base query and its display-number column for a
     * subject type. Only buyer/supplier subject types are reachable here;
     * an internal-only type would signal a misrouted party.
     *
     * @return array{0: Builder<\Illuminate\Database\Eloquent\Model>, 1: string}
     */
    private function baseSubjectQuery(Request $request, string $subjectType): array
    {
        /** @var array{0: Builder<\Illuminate\Database\Eloquent\Model>, 1: string} $result */
        $result = match ($subjectType) {
            'request' => [Request::query()->whereKey($request->getKey()), 'request_number'],
            'buyer_quote' => [$request->buyerQuotes()->getQuery(), 'quote_number'],
            'buyer_invoice' => [$request->buyerInvoices()->getQuery(), 'invoice_number'],
            'buyer_payment' => [
                BuyerPayment::query()->whereIn('buyer_invoice_id', $request->buyerInvoices()->select('id')),
                'payment_number',
            ],
            'shipment' => [$request->shipments()->getQuery(), 'shipment_number'],
            'supplier_quote' => [$request->supplierQuotes()->getQuery(), 'quote_number'],
            'supplier_order' => [$request->supplierOrders()->getQuery(), 'po_number'],
            'supplier_invoice' => [$request->supplierInvoices()->getQuery(), 'invoice_number'],
            'supplier_payment' => [
                SupplierPayment::query()->whereIn('supplier_invoice_id', $request->supplierInvoices()->select('id')),
                'payment_number',
            ],
            default => throw new RuntimeException(
                "PortalTimelineSource has no request-scoped query for subject '{$subjectType}'; portals must only receive buyer/supplier subject rules."
            ),
        };

        return $result;
    }

    /**
     * Translate a SubjectRule's declarative constraints into query
     * predicates, resolving dotted relation paths (e.g. 'request.buyer_id',
     * 'buyerInvoice.request.buyer_id') into nested whereHas clauses.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyRule(Builder $query, SubjectRule $rule): void
    {
        foreach ($rule->where as $path => $value) {
            $this->applyWhere($query, $path, $value);
        }

        foreach ($rule->whereNot as $column => $values) {
            $query->whereNotIn($column, $values);
        }
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyWhere(Builder $query, string $path, mixed $value): void
    {
        if (! str_contains($path, '.')) {
            $query->where($path, $value);

            return;
        }

        [$relation, $rest] = explode('.', $path, 2);

        $query->whereHas($relation, function (Builder $sub) use ($rest, $value): void {
            $this->applyWhere($sub, $rest, $value);
        });
    }

    /**
     * One whereIn query over the allow-listed (subject_type, subject_id)
     * tuples on the live activity_log, causer eager-loaded.
     *
     * @param  array<string, array<int, string|null>>  $subjects
     * @return Collection<int, TimelineEntry>
     */
    private function activityEntries(array $subjects): Collection
    {
        $subjects = array_filter($subjects, fn (array $numbers): bool => $numbers !== []);

        if ($subjects === []) {
            return collect();
        }

        return ActivityLog::query()
            ->with('causer')
            ->where(function (Builder $query) use ($subjects): void {
                foreach ($subjects as $subjectType => $numbers) {
                    $query->orWhere(function (Builder $tuple) use ($subjectType, $numbers): void {
                        $tuple->where('subject_type', $subjectType)
                            ->whereIn('subject_id', array_keys($numbers));
                    });
                }
            })
            ->get()
            ->map(function (ActivityLog $activity) use ($subjects): TimelineEntry {
                $subjectType = (string) $activity->subject_type;
                $subjectId = (int) $activity->subject_id;
                $subjectNumber = $subjects[$subjectType][$subjectId] ?? null;
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
                        'attributes' => $attributes,
                        'old' => (array) $activity->properties->get('old', []),
                    ],
                );
            });
    }

    /**
     * Uploads on the party's allow-listed subjects, filtered by the party's
     * media rules. Fail-closed: a collection without a rule is dropped, an
     * uploader stamp outside the rule's allowed actor types is dropped, and
     * unstamped media is dropped unless the rule explicitly opts in.
     *
     * @param  array<string, array<int, string|null>>  $subjects
     * @return Collection<int, TimelineEntry>
     */
    private function mediaEntries(array $subjects, TimelineParty $party): Collection
    {
        $subjects = array_filter($subjects, fn (array $numbers): bool => $numbers !== []);
        $rules = $this->audience->mediaRules($party);

        if ($subjects === [] || $rules === []) {
            return collect();
        }

        $collections = array_keys($rules);

        $media = Media::query()
            ->whereIn('collection_name', $collections)
            ->where(function (Builder $query) use ($subjects): void {
                foreach ($subjects as $subjectType => $numbers) {
                    $query->orWhere(function (Builder $tuple) use ($subjectType, $numbers): void {
                        $tuple->where('model_type', $subjectType)
                            ->whereIn('model_id', array_keys($numbers));
                    });
                }
            })
            ->get();

        return $media
            ->filter(fn (Media $item): bool => $this->mediaPasses($item, $rules[$item->collection_name] ?? null))
            ->map(function (Media $item) use ($subjects): TimelineEntry {
                $actorType = ActorType::tryFrom((string) $item->getCustomProperty('uploader_actor_type')) ?? ActorType::System;

                return new TimelineEntry(
                    actorLabel: 'Uploaded',
                    actorType: $actorType,
                    entryType: TimelineAudience::ENTRY_MEDIA,
                    event: 'uploaded',
                    headline: sprintf('uploaded %s → %s', $item->file_name, Str::headline($item->collection_name)),
                    subjectType: (string) $item->model_type,
                    subjectId: (int) $item->model_id,
                    subjectNumber: $subjects[(string) $item->model_type][(int) $item->model_id] ?? null,
                    changedFieldCount: 0,
                    occurredAt: $item->created_at->toImmutable(),
                );
            })
            ->values();
    }

    /**
     * Whether a media row satisfies its collection's rule (fail-closed).
     */
    private function mediaPasses(Media $item, ?MediaRule $rule): bool
    {
        if (! $rule instanceof \App\Services\Timeline\MediaRule) {
            return false;
        }

        $stamp = $item->getCustomProperty('uploader_actor_type');

        if ($stamp === null) {
            return $rule->allowUnstamped;
        }

        $actorType = ActorType::tryFrom((string) $stamp);

        if ($rule->uploaderActorTypes !== null && ($actorType === null || ! in_array($actorType, $rule->uploaderActorTypes, true))) {
            return false;
        }

        if ($rule->uploaderCompanyId !== null) {
            $company = $item->getCustomProperty('uploader_company_id');

            if ($company === null || (int) $company !== $rule->uploaderCompanyId) {
                return false;
            }
        }

        return true;
    }

    /**
     * Note lane, hard-scoped by the audience helper's note-visibility scope
     * (design D2). Only notes matching the party's visibility AND identity are
     * ever SELECTED — an Internal note is never queried for a portal, and a
     * supplier-shared note is pinned to a single supplier company so a shared
     * request cannot leak one supplier's note to another. A System-style empty
     * scope selects nothing.
     *
     * @return Collection<int, TimelineEntry>
     */
    private function noteEntries(Request $request, TimelineParty $party): Collection
    {
        $scope = $this->audience->noteVisibilityScope($party);

        if ($scope['all'] === false && $scope['visibility'] === null) {
            return collect();
        }

        $query = RequestNote::query()
            ->with(['author', 'media'])
            ->where('request_id', $request->getKey());

        if ($scope['visibility'] !== null) {
            $query->where('visibility', $scope['visibility']->value);
        }

        if ($scope['buyerCompanyId'] !== null) {
            $query->whereHas('request', function (Builder $sub) use ($scope): void {
                $sub->where('buyer_id', $scope['buyerCompanyId']);
            });
        }

        if ($scope['supplierCompanyId'] !== null) {
            $query->where('audience_company_id', $scope['supplierCompanyId']);
        }

        return $query->get()->map(function (RequestNote $note) use ($request): TimelineEntry {
            $attachments = array_values($note->getMedia(RequestNote::ATTACHMENTS_COLLECTION)
                ->map(fn (Media $media): string => (string) $media->file_name)
                ->all());

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
     * both surface through views that render only the headline.
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

    /**
     * Apply the party's presentation redaction to one entry: collapse the
     * causer to the generic party-facing label (never a staff name), re-map
     * any stage value through the buyer presenter, rewrite a stage-change
     * headline to the mapped label, and drop links outside the allow-list.
     */
    private function redact(TimelineEntry $entry, TimelineParty $party, RedactionRules $rules): TimelineEntry
    {
        $properties = $this->remapStageProperties($entry->properties, $rules);
        $actorLabel = $rules->collapseCauser
            ? $this->collapsedLabel($entry->actorType, $party, $rules)
            : $entry->actorLabel;

        $headline = $entry->headline;

        if ($entry->subjectType === 'request' && $rules->remapStageLabels && isset($properties['attributes']['stage'])) {
            $headline = 'Status updated to '.$properties['attributes']['stage'];
        }

        $url = $rules->allowsLinkRoute($entry->url) ? $entry->url : null;

        return new TimelineEntry(
            actorLabel: $actorLabel,
            actorType: $entry->actorType,
            entryType: $entry->entryType,
            event: $entry->event,
            headline: $headline,
            subjectType: $entry->subjectType,
            subjectId: $entry->subjectId,
            subjectNumber: $entry->subjectNumber,
            changedFieldCount: $entry->changedFieldCount,
            occurredAt: $entry->occurredAt,
            url: $url,
            properties: $properties,
            lane: $entry->lane,
            body: $entry->body,
            attachments: $entry->attachments,
        );
    }

    /**
     * The party-facing causer label: the party's own actions read as 'You',
     * everything else collapses to the generic label ('Your team').
     */
    private function collapsedLabel(ActorType $actorType, TimelineParty $party, RedactionRules $rules): string
    {
        if ($actorType === $party->actorType) {
            return 'You';
        }

        return $rules->genericCauserLabel ?? 'System';
    }

    /**
     * Re-map any raw stage value in a diff payload to the party-facing label
     * so a raw enum value (e.g. 'awaiting_supplier_response') never renders.
     *
     * @param  array<string, mixed>|null  $properties
     * @return array<string, mixed>|null
     */
    private function remapStageProperties(?array $properties, RedactionRules $rules): ?array
    {
        if ($properties === null || ! $rules->remapStageLabels) {
            return $properties;
        }

        foreach (['attributes', 'old'] as $bucket) {
            if (! isset($properties[$bucket])) {
                continue;
            }
            if (! is_array($properties[$bucket])) {
                continue;
            }
            if (! array_key_exists('stage', $properties[$bucket])) {
                continue;
            }
            $raw = $properties[$bucket]['stage'];
            $stage = $raw instanceof RequestStage ? $raw : RequestStage::tryFrom((string) $raw);

            if ($stage !== null) {
                $properties[$bucket]['stage'] = $rules->stageLabel($stage);
            }
        }

        return $properties;
    }

    /**
     * Actor types whose entries a party must never see, even on an
     * allow-listed subject — a buyer never sees supplier- or admin-authored
     * rows, and vice versa (defense in depth over subject selection).
     *
     * @return list<ActorType>
     */
    private function disallowedActorTypes(TimelineParty $party): array
    {
        return match ($party->actorType) {
            ActorType::Buyer => [ActorType::Supplier, ActorType::Admin],
            ActorType::Supplier => [ActorType::Buyer, ActorType::Admin],
            default => [],
        };
    }

    private function guardPortal(TimelineParty $party): void
    {
        if ($party->isInternal()) {
            throw new InvalidArgumentException(
                'PortalTimelineSource serves buyer/supplier parties only; internal surfaces use RequestTimelineSource.'
            );
        }
    }
}
