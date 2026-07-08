<?php

declare(strict_types=1);

use App\Data\TimelineEntry;
use App\Enums\ActorType;
use App\Enums\BuyerQuoteStatus;
use App\Enums\RequestStage;
use App\Enums\ShipmentType;
use App\Models\Concerns\LogsErpActivity;
use App\Services\Timeline\SubjectRule;
use App\Services\Timeline\TimelineAudience;
use App\Services\Timeline\TimelineParty;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;

beforeEach(function (): void {
    $this->audience = new TimelineAudience;
});

it('gives staff and admin the full internal subject set', function (): void {
    $staffSubjects = array_keys($this->audience->subjectRules(TimelineParty::staff()));
    $adminSubjects = array_keys($this->audience->subjectRules(TimelineParty::admin()));

    expect($staffSubjects)->toEqualCanonicalizing(TimelineAudience::INTERNAL_SUBJECT_TYPES)
        ->and($adminSubjects)->toEqualCanonicalizing($staffSubjects)
        ->and($staffSubjects)->toContain('supplier_quote', 'quotation_evaluation', 'profit_and_loss', 'buyer_order');
});

it('gives the buyer a strict subset of the internal set without supplier, evaluation, PNL, or buyer order subjects', function (): void {
    $buyerSubjects = array_keys($this->audience->subjectRules(TimelineParty::buyer(42)));
    $internalSubjects = array_keys($this->audience->subjectRules(TimelineParty::staff()));

    expect($buyerSubjects)->toEqualCanonicalizing(['request', 'buyer_quote', 'shipment', 'buyer_invoice', 'buyer_payment'])
        ->and(array_diff($buyerSubjects, $internalSubjects))->toBe([])
        ->and(count($buyerSubjects))->toBeLessThan(count($internalSubjects));

    foreach (['supplier_quote', 'supplier_order', 'supplier_invoice', 'supplier_payment', 'quotation_evaluation', 'profit_and_loss', 'buyer_order'] as $forbidden) {
        expect($buyerSubjects)->not->toContain($forbidden);
    }
});

it('identity-scopes every buyer rule to the buyer company and hides drafts and inbound shipments', function (): void {
    $rules = $this->audience->subjectRules(TimelineParty::buyer(42));

    foreach ($rules as $rule) {
        expect($rule)->toBeInstanceOf(SubjectRule::class)
            ->and($rule->where)->toContain(42);
    }

    expect($rules['buyer_quote']->whereNot)->toBe(['status' => [BuyerQuoteStatus::DRAFT->value]])
        ->and($rules['shipment']->where['type'])->toBe(ShipmentType::OUTBOUND->value)
        ->and($rules['request']->where)->toBe(['buyer_id' => 42]);
});

it('scopes a supplier party to its own documents only, never another supplier', function (): void {
    $rules = $this->audience->subjectRules(TimelineParty::supplier(42));
    $internalSubjects = array_keys($this->audience->subjectRules(TimelineParty::staff()));

    expect(array_keys($rules))->toEqualCanonicalizing(['supplier_quote', 'supplier_order', 'supplier_invoice', 'supplier_payment'])
        ->and(array_diff(array_keys($rules), $internalSubjects))->toBe([]);

    foreach ($rules as $rule) {
        expect($rule->where)->toContain(42)
            ->and($rule->where)->not->toContain(43);
    }

    expect($rules['supplier_quote']->where)->toBe(['supplier_id' => 42])
        ->and($rules['supplier_payment']->where)->toBe(['supplierInvoice.supplier_id' => 42]);
});

it('gives supplier 42 rules structurally distinct from supplier 43', function (): void {
    $rulesA = $this->audience->subjectRules(TimelineParty::supplier(42));
    $rulesB = $this->audience->subjectRules(TimelineParty::supplier(43));

    foreach ($rulesA as $subjectType => $rule) {
        expect($rule->where)->not->toEqual($rulesB[$subjectType]->where);
    }
});

it('classifies every model using LogsErpActivity as buyer-visible or explicitly excluded with a reason', function (): void {
    $morphMap = Relation::morphMap();
    $buyerSubjects = array_keys($this->audience->subjectRules(TimelineParty::buyer(1)));
    $excluded = TimelineAudience::BUYER_EXCLUDED_SUBJECT_TYPES;

    $loggedModelClasses = collect(glob(app_path('Models').'/*.php'))
        ->map(fn (string $path): string => 'App\\Models\\'.basename($path, '.php'))
        ->filter(fn (string $class): bool => class_exists($class)
            && in_array(LogsErpActivity::class, class_uses_recursive($class), true))
        ->values();

    expect($loggedModelClasses)->not->toBeEmpty();

    foreach ($loggedModelClasses as $class) {
        $alias = array_search($class, $morphMap, true);

        expect($alias)->not->toBeFalse("{$class} uses LogsErpActivity but has no morph alias.");

        $classified = in_array($alias, $buyerSubjects, true) || array_key_exists($alias, $excluded);

        expect($classified)->toBeTrue(
            "{$class} ({$alias}) uses LogsErpActivity but is neither in the buyer allow-list nor explicitly excluded with a reason."
        );
    }

    foreach ($excluded as $alias => $reason) {
        expect($reason)->toBeString()->not->toBe('')
            ->and($buyerSubjects)->not->toContain($alias);
    }
});

it('reserves the credit-ledger lane for internal parties only', function (): void {
    expect($this->audience->entryTypes(TimelineParty::staff()))->toContain(TimelineAudience::ENTRY_CREDIT)
        ->and($this->audience->entryTypes(TimelineParty::admin()))->toContain(TimelineAudience::ENTRY_CREDIT)
        ->and($this->audience->entryTypes(TimelineParty::buyer(42)))->toEqualCanonicalizing([TimelineAudience::ENTRY_ACTIVITY, TimelineAudience::ENTRY_MEDIA, TimelineAudience::ENTRY_NOTE])
        ->and($this->audience->entryTypes(TimelineParty::supplier(42)))->not->toContain(TimelineAudience::ENTRY_CREDIT);
});

it('fails closed on media for portal parties and open for staff', function (): void {
    $staffRules = $this->audience->mediaRules(TimelineParty::staff());
    $buyerRules = $this->audience->mediaRules(TimelineParty::buyer(42));
    $supplierRules = $this->audience->mediaRules(TimelineParty::supplier(42));

    expect($staffRules['*']->allowUnstamped)->toBeTrue()
        ->and(array_keys($buyerRules))->toEqualCanonicalizing(['attachments', 'buyer_po'])
        ->and($buyerRules['attachments']->uploaderActorTypes)->toBe([ActorType::Buyer])
        ->and($buyerRules['attachments']->allowUnstamped)->toBeFalse()
        ->and($buyerRules['buyer_po']->allowUnstamped)->toBeFalse()
        ->and($supplierRules['attachments']->uploaderActorTypes)->toBe([ActorType::Supplier])
        ->and($supplierRules['attachments']->uploaderCompanyId)->toBe(42)
        ->and($supplierRules['attachments']->allowUnstamped)->toBeFalse();
});

it('redacts causer, stage labels, and links for the buyer but not for staff', function (): void {
    $staff = $this->audience->redactionRules(TimelineParty::staff());
    $buyer = $this->audience->redactionRules(TimelineParty::buyer(42));

    expect($staff->collapseCauser)->toBeFalse()
        ->and($staff->remapStageLabels)->toBeFalse()
        ->and($staff->stageLabel(RequestStage::DRAFT))->toBe(RequestStage::DRAFT->getLabel())
        ->and($staff->allowsLinkRoute('filament.app.resources.requests.view'))->toBeTrue()
        ->and($buyer->collapseCauser)->toBeTrue()
        ->and($buyer->genericCauserLabel)->toBe('Your team')
        ->and($buyer->stageLabel(RequestStage::DRAFT))->toBe('Request Received')
        ->and($buyer->allowsLinkRoute('filament.customer.resources.requests.view'))->toBeTrue()
        ->and($buyer->allowsLinkRoute('filament.app.resources.requests.view'))->toBeFalse()
        ->and($buyer->allowsLinkRoute('filament.sysadmin.pages.dashboard'))->toBeFalse()
        ->and($buyer->allowsLinkRoute(null))->toBeFalse();
});

it('builds a timeline entry DTO carrying actor, subject reference, diff count, and lane', function (): void {
    $entry = new TimelineEntry(
        actorLabel: 'Sarah',
        actorType: ActorType::Staff,
        entryType: TimelineAudience::ENTRY_ACTIVITY,
        event: 'updated',
        headline: 'Buyer Quote BQ-2026-088 updated — 4 fields',
        subjectType: 'buyer_quote',
        subjectId: 7,
        subjectNumber: 'BQ-2026-088',
        changedFieldCount: 4,
        occurredAt: CarbonImmutable::parse('2026-07-07 14:32:00'),
        properties: ['attributes' => ['total' => 950.0], 'old' => ['total' => 1200.0]],
        lane: null,
    );

    expect($entry->actorType)->toBe(ActorType::Staff)
        ->and($entry->subjectNumber)->toBe('BQ-2026-088')
        ->and($entry->changedFieldCount)->toBe(4)
        ->and($entry->url)->toBeNull()
        ->and($entry->properties)->toHaveKeys(['attributes', 'old']);
});
