<?php

declare(strict_types=1);

namespace App\Services\Timeline;

use App\Enums\ActorType;
use App\Enums\BuyerQuoteStatus;
use App\Enums\NoteVisibility;
use App\Enums\ShipmentType;
use InvalidArgumentException;

/**
 * Audience-scoped visibility helper — the single choke point every request
 * timeline surface (internal, buyer portal, later supplier portal) resolves
 * its visibility through (design D2).
 *
 * The model is ADDITIVE: a party gets a positive allow-list of subject
 * types (with identity scoping baked into each rule), entry-type lanes,
 * media collections, and redaction rules. Anything unlisted is denied by
 * default — non-staff surfaces never load the internal feed and filter it
 * down, because a positive allow-list cannot leak what it never selects.
 */
final readonly class TimelineAudience
{
    public const string ENTRY_ACTIVITY = 'activity';

    public const string ENTRY_MEDIA = 'media';

    public const string ENTRY_CREDIT = 'credit';

    public const string ENTRY_NOTE = 'note';

    /**
     * Full internal subject set: the request plus every logged request
     * child (interim enumeration per design D7; swaps to the
     * parent_type/parent_id predicate once line-item logging lands).
     *
     * @var list<string>
     */
    public const array INTERNAL_SUBJECT_TYPES = [
        'request',
        'buyer_quote',
        'supplier_quote',
        'buyer_order',
        'supplier_order',
        'buyer_invoice',
        'supplier_invoice',
        'buyer_payment',
        'supplier_payment',
        'shipment',
        'quotation_evaluation',
        'profit_and_loss',
        'acceptance_report',
        'goods_receive_batch',
        'request_item',
        'buyer_quote_item',
        'supplier_quote_item',
        'buyer_order_item',
        'supplier_order_item',
        'buyer_invoice_item',
        'supplier_invoice_item',
        'shipment_item',
    ];

    /**
     * Logged subjects deliberately EXCLUDED from the buyer allow-list,
     * each with the reason. Every model using LogsErpActivity must appear
     * either in the buyer allow-list or here — an architecture test fails
     * when a new logged model is silently unclassified (design D2).
     *
     * BuyerOrder in particular is buyer-owned and logged, but its audited
     * attributes include internal credit mechanics (credit_released,
     * payment_terms_days); the buyer sees BuyerInvoice + BuyerPayment as
     * the buyer-facing money artifacts instead.
     *
     * @var array<string, string> morph alias => exclusion reason
     */
    public const array BUYER_EXCLUDED_SUBJECT_TYPES = [
        'buyer_order' => 'Audited attributes carry internal credit mechanics (credit_released, payment_terms_days); buyers see BuyerInvoice + BuyerPayment instead.',
        'supplier_quote' => 'Supplier cost figures; any supplier price reaching a buyer is a margin leak.',
        'supplier_order' => 'Supplier-side purchasing document; internal cost data.',
        'supplier_invoice' => 'Supplier-side billing; internal cost data.',
        'supplier_payment' => 'Supplier-side money movement; internal cost data.',
        'quotation_evaluation' => 'Internal sourcing analysis comparing supplier costs.',
        'profit_and_loss' => 'Internal margin analysis.',
        'acceptance_report' => 'Internal fulfillment record; not classified buyer-facing in v1.',
        'goods_receive_batch' => 'Internal inbound goods handling.',
        'company' => 'Team master data whose audited attributes include internal credit fields (credit_used, credit_limit).',
        'request_item' => 'Line-level change surfaced only on the internal timeline, grouped under its parent request.',
        'buyer_quote_item' => 'Line-level change surfaced only on the internal timeline, grouped under its parent buyer quote.',
        'supplier_quote_item' => 'Supplier cost line; internal-only, never buyer-facing.',
        'buyer_order_item' => 'Line-level change surfaced only on the internal timeline, grouped under its parent buyer order.',
        'supplier_order_item' => 'Supplier-side purchasing line; internal cost data.',
        'buyer_invoice_item' => 'Line-level change surfaced only on the internal timeline, grouped under its parent buyer invoice.',
        'supplier_invoice_item' => 'Supplier-side billing line; internal cost data.',
        'shipment_item' => 'Internal fulfillment line; not classified buyer-facing in v1.',
    ];

    /**
     * The additive subject allow-list for a party: morph alias => scoping
     * rule. Subjects without a rule are denied by default.
     *
     * @return array<string, SubjectRule>
     */
    public function subjectRules(TimelineParty $party): array
    {
        return match ($party->actorType) {
            ActorType::Staff, ActorType::Admin => $this->internalSubjectRules(),
            ActorType::Buyer => $this->buyerSubjectRules($this->requireCompanyId($party)),
            ActorType::Supplier => $this->supplierSubjectRules($this->requireCompanyId($party)),
            ActorType::System => [],
        };
    }

    /**
     * Entry-type lanes the party may load. The credit-ledger lane is
     * internal-only in v1 (design D8).
     *
     * @return list<string>
     */
    public function entryTypes(TimelineParty $party): array
    {
        if ($party->isInternal()) {
            return [self::ENTRY_ACTIVITY, self::ENTRY_MEDIA, self::ENTRY_CREDIT, self::ENTRY_NOTE];
        }

        if ($party->actorType === ActorType::System) {
            return [];
        }

        return [self::ENTRY_ACTIVITY, self::ENTRY_MEDIA, self::ENTRY_NOTE];
    }

    /**
     * Which RequestNotes a party may load, as a declarative scope the timeline
     * sources translate into a query (never Internal notes to a portal; never
     * one supplier's notes to another).
     *
     *  - staff/admin: every note on the request ('all' => true);
     *  - buyer:{companyId}: notes with visibility=Buyer, further pinned to a
     *    request the company owns (request.buyer_id = companyId);
     *  - supplier:{companyId}: notes with visibility=Supplier pinned to that
     *    supplier (audience_company_id = companyId);
     *  - system: no notes.
     *
     * @return array{all: bool, visibility: NoteVisibility|null, buyerCompanyId: int|null, supplierCompanyId: int|null}
     */
    public function noteVisibilityScope(TimelineParty $party): array
    {
        return match ($party->actorType) {
            ActorType::Staff, ActorType::Admin => [
                'all' => true,
                'visibility' => null,
                'buyerCompanyId' => null,
                'supplierCompanyId' => null,
            ],
            ActorType::Buyer => [
                'all' => false,
                'visibility' => NoteVisibility::Buyer,
                'buyerCompanyId' => $this->requireCompanyId($party),
                'supplierCompanyId' => null,
            ],
            ActorType::Supplier => [
                'all' => false,
                'visibility' => NoteVisibility::Supplier,
                'buyerCompanyId' => null,
                'supplierCompanyId' => $this->requireCompanyId($party),
            ],
            ActorType::System => [
                'all' => false,
                'visibility' => null,
                'buyerCompanyId' => null,
                'supplierCompanyId' => null,
            ],
        };
    }

    /**
     * Media collections the party may see, keyed by collection name
     * ('*' = every collection, internal parties only). Collections without
     * a rule are denied; unstamped media is fail-closed for portal parties.
     *
     * @return array<string, MediaRule>
     */
    public function mediaRules(TimelineParty $party): array
    {
        return match ($party->actorType) {
            ActorType::Staff, ActorType::Admin => [
                '*' => new MediaRule('*', null, true),
            ],
            ActorType::Buyer => [
                'attachments' => new MediaRule('attachments', [ActorType::Buyer], false),
                'buyer_po' => new MediaRule('buyer_po', null, false),
            ],
            ActorType::Supplier => [
                'attachments' => new MediaRule(
                    'attachments',
                    [ActorType::Supplier],
                    false,
                    $this->requireCompanyId($party),
                ),
            ],
            ActorType::System => [],
        };
    }

    public function redactionRules(TimelineParty $party): RedactionRules
    {
        return match ($party->actorType) {
            ActorType::Staff, ActorType::Admin => new RedactionRules(
                collapseCauser: false,
                genericCauserLabel: null,
                remapStageLabels: false,
                allowedLinkRoutePrefixes: ['filament.app.'],
            ),
            ActorType::Buyer => new RedactionRules(
                collapseCauser: true,
                genericCauserLabel: 'Your team',
                remapStageLabels: true,
                allowedLinkRoutePrefixes: ['filament.buyer.resources.requests.', 'buyer.documents.'],
            ),
            ActorType::Supplier => new RedactionRules(
                collapseCauser: true,
                genericCauserLabel: 'Your team',
                remapStageLabels: true,
                allowedLinkRoutePrefixes: ['filament.supplier.resources.', 'supplier.documents.'],
            ),
            ActorType::System => new RedactionRules(
                collapseCauser: true,
                genericCauserLabel: null,
                remapStageLabels: true,
                allowedLinkRoutePrefixes: [],
            ),
        };
    }

    /**
     * @return array<string, SubjectRule>
     */
    private function internalSubjectRules(): array
    {
        $rules = [];

        foreach (self::INTERNAL_SUBJECT_TYPES as $subjectType) {
            $rules[$subjectType] = new SubjectRule($subjectType);
        }

        return $rules;
    }

    /**
     * @return array<string, SubjectRule>
     */
    private function buyerSubjectRules(int $companyId): array
    {
        return [
            'request' => new SubjectRule('request', ['buyer_id' => $companyId]),
            'buyer_quote' => new SubjectRule(
                'buyer_quote',
                ['buyer_id' => $companyId],
                ['status' => [BuyerQuoteStatus::DRAFT->value]],
            ),
            'shipment' => new SubjectRule('shipment', [
                'request.buyer_id' => $companyId,
                'type' => ShipmentType::OUTBOUND->value,
            ]),
            'buyer_invoice' => new SubjectRule('buyer_invoice', ['request.buyer_id' => $companyId]),
            'buyer_payment' => new SubjectRule('buyer_payment', ['buyerInvoice.request.buyer_id' => $companyId]),
        ];
    }

    /**
     * Supplier parties see only their own supplier-side documents, keyed
     * to the party's company id (design phase 3 — allow-list only, no UI yet).
     *
     * @return array<string, SubjectRule>
     */
    private function supplierSubjectRules(int $companyId): array
    {
        return [
            'supplier_quote' => new SubjectRule('supplier_quote', ['supplier_id' => $companyId]),
            'supplier_order' => new SubjectRule('supplier_order', ['supplier_id' => $companyId]),
            'supplier_invoice' => new SubjectRule('supplier_invoice', ['supplier_id' => $companyId]),
            'supplier_payment' => new SubjectRule('supplier_payment', ['supplierInvoice.supplier_id' => $companyId]),
        ];
    }

    private function requireCompanyId(TimelineParty $party): int
    {
        if ($party->companyId === null) {
            throw new InvalidArgumentException('Buyer and supplier timeline parties must carry a company id.');
        }

        return $party->companyId;
    }
}
