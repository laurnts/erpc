<?php

declare(strict_types=1);

use App\Actions\SupplierPortal\StampSupplierQuoteSent;
use App\Enums\PortalType;
use App\Enums\SupplierQuoteStatus;
use App\Enums\SupplierQuoteSubmissionMethod;
use App\Filament\Resources\RequestResource\Pages\EditRequest;
use App\Filament\Resources\RequestResource\RelationManagers\ItemsRelationManager;
use App\Filament\Supplier\Resources\SupplierRequestResource\Pages\ListSupplierRequests;
use App\Filament\Supplier\Resources\SupplierRequestResource\Pages\ViewSupplierRequest;
use App\Jobs\Erp\CheckAwaitingSupplierQuotesJob;
use App\Mail\Erp\QuoteToSupplierMail;
use App\Models\Article;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierArticle;
use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Erp\AwaitingSupplierQuoteNotification;
use App\Notifications\SupplierQuoteDeclinedNotification;
use App\Notifications\SupplierQuoteSubmittedNotification;
use App\Services\Portal\SupplierPortalContext;
use App\Services\SupplierPortal\SupplierRequestStatusPresenter;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    config(['app.supplier_portal_enabled' => true]);

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team->users()->attach($this->admin, ['role' => 'admin']);
    $this->admin->switchTeam($this->team);

    $this->supplier = Company::factory()->supplier()->for($this->team)->create([
        'name' => 'Own Supplier Co',
        'email' => 'sales@own-supplier.test',
    ]);

    $this->portalUser = User::factory()->create(['email' => 'rfq@supplier.test']);

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $this->supplier->getKey(),
        'user_id' => $this->portalUser->getKey(),
        'portal' => PortalType::Supplier,
        'invited_by' => $this->admin->getKey(),
        'is_active' => true,
    ]);

    $this->usd = Currency::factory()->usd()->create();
    $this->eur = Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€']);

    ExchangeRate::factory()->create([
        'team_id' => $this->team->getKey(),
        'creator_id' => $this->admin->getKey(),
        'from_currency_id' => $this->eur->getKey(),
        'to_currency_id' => $this->usd->getKey(),
        'rate' => '1.1000000000',
        'effective_date' => now()->toDateString(),
    ]);

    $this->buyer = Company::factory()->buyer()->for($this->team)->create(['name' => 'Confidential Buyer Ltd']);

    $this->request = Request::factory()->recycle($this->team)->create([
        'buyer_id' => $this->buyer->getKey(),
        'creator_id' => $this->admin->getKey(),
        'title' => 'Confidential Sourcing Project',
    ]);

    $this->quote = SupplierQuote::factory()
        ->recycle($this->team)
        ->forRequest($this->request)
        ->forSupplier($this->supplier)
        ->withCurrency($this->usd)
        ->pending()
        ->sentToSupplier()
        ->validFor(30)
        ->create([
            'creator_id' => $this->admin->getKey(),
            'notes' => null,
            'internal_notes' => 'INTERNAL-EYES-ONLY',
        ]);

    $this->quoteItem = $this->quote->items()->create([
        'description' => 'Steel Pipe 5m',
        'quantity' => '10',
        'unit' => 'pcs',
        'sort_order' => 0,
    ]);

    $this->actingAs($this->portalUser, 'supplier');
    Filament::setCurrentPanel('supplier');
    app(SupplierPortalContext::class)->setCompany($this->supplier->getKey());
});

describe('RFQ visibility', function (): void {
    it('lists only own sent quotes — unsent and foreign quotes never surface', function (): void {
        $unsent = SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($this->supplier)
            ->withCurrency($this->usd)
            ->pending()
            ->validFor(30)
            ->create(['creator_id' => $this->admin->getKey()]);

        $rival = Company::factory()->supplier()->for($this->team)->create(['name' => 'Rival Supplier Co']);
        $foreign = SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($rival)
            ->withCurrency($this->usd)
            ->pending()
            ->sentToSupplier()
            ->validFor(30)
            ->create(['creator_id' => $this->admin->getKey()]);

        livewire(ListSupplierRequests::class)
            ->assertCanSeeTableRecords([$this->quote])
            ->assertCanNotSeeTableRecords([$unsent, $foreign]);

        $scopedIds = SupplierQuote::query()
            ->forSupplierPortal($this->supplier->getKey())
            ->pluck('id')
            ->all();

        expect($scopedIds)->toContain($this->quote->getKey())
            ->not->toContain($unsent->getKey())
            ->not->toContain($foreign->getKey());
    });

    it('renders the staff-style header on the quote request detail page', function (): void {
        livewire(ViewSupplierRequest::class, ['record' => $this->quote->getKey()])
            ->assertOk()
            ->assertSee('Reference')
            ->assertSee($this->quote->quote_number)
            ->assertSee('Valid Until');
    });

    it('denies unsent quotes by policy and resolves them as not found on the view page', function (): void {
        $unsent = SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($this->supplier)
            ->withCurrency($this->usd)
            ->pending()
            ->validFor(30)
            ->create(['creator_id' => $this->admin->getKey()]);

        expect($this->portalUser->can('view', $this->quote))->toBeTrue()
            ->and($this->portalUser->can('view', $unsent))->toBeFalse();

        expect(fn () => livewire(ViewSupplierRequest::class, ['record' => $unsent->getKey()]))
            ->toThrow(ModelNotFoundException::class);
    });

    it('shows only open sent quotes under the Open status filter, excluding unsent and declined ones', function (): void {
        $unsent = SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($this->supplier)
            ->withCurrency($this->usd)
            ->pending()
            ->validFor(30)
            ->create(['creator_id' => $this->admin->getKey()]);

        $declined = SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($this->supplier)
            ->withCurrency($this->usd)
            ->pending()
            ->sentToSupplier()
            ->declined()
            ->validFor(30)
            ->create(['creator_id' => $this->admin->getKey()]);

        livewire(ListSupplierRequests::class)
            ->filterTable('status_group', 'open')
            ->assertCanSeeTableRecords([$this->quote])
            ->assertCanNotSeeTableRecords([$unsent, $declined]);
    });

    it('treats a received quote as submitted, not open', function (): void {
        $this->quote->forceFill(['status' => SupplierQuoteStatus::RECEIVED])->saveQuietly();

        livewire(ListSupplierRequests::class)
            ->filterTable('status_group', 'submitted')
            ->assertCanSeeTableRecords([$this->quote]);

        livewire(ListSupplierRequests::class)
            ->filterTable('status_group', 'open')
            ->assertCanNotSeeTableRecords([$this->quote]);
    });
});

describe('Submitting a quote', function (): void {
    it('submits prices with a server-resolved exchange rate and fires the standard machinery', function (): void {
        Notification::fake();

        livewire(ViewSupplierRequest::class, ['record' => $this->quote->getKey()])
            ->callAction('submit', data: [
                'item_prices' => [$this->quoteItem->getKey() => '100'],
                'currency_id' => $this->eur->getKey(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'notes' => 'Our best price, delivery in 2 weeks.',
            ])
            ->assertHasNoActionErrors();

        $quote = $this->quote->refresh();

        expect($quote->status)->toBe(SupplierQuoteStatus::RECEIVED)
            ->and($quote->submitted_via)->toBe(SupplierQuoteSubmissionMethod::Portal)
            ->and($quote->submitted_at)->not->toBeNull()
            ->and($quote->submitted_by_user_id)->toBe($this->portalUser->getKey())
            ->and($quote->currency_id)->toBe($this->eur->getKey())
            ->and($quote->exchange_rate)->toBe('1.10000000')
            ->and($quote->notes)->toBe('Our best price, delivery in 2 weeks.')
            ->and((float) $quote->total)->toBe(1000.0)
            ->and((float) $quote->total_base)->toBe(1100.0);

        expect((float) $this->quoteItem->refresh()->unit_price)->toBe(100.0);

        Notification::assertSentTo($this->admin, SupplierQuoteSubmittedNotification::class);
    });

    it('stamps the submitted quotation document with a v3 document path', function (): void {
        Notification::fake();

        livewire(ViewSupplierRequest::class, ['record' => $this->quote->getKey()])
            ->callAction('submit', data: [
                'item_prices' => [$this->quoteItem->getKey() => '100'],
                'currency_id' => $this->eur->getKey(),
                'quotation_file' => [\Illuminate\Http\UploadedFile::fake()->createWithContent(
                    'quotation.pdf',
                    "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n",
                )],
            ])
            ->assertHasNoActionErrors();

        $media = $this->quote->refresh()->getFirstMedia('quotation');

        expect($media)->not->toBeNull()
            ->and($media->getCustomProperty(\App\Support\Media\DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(\App\Support\Media\DocumentPathGenerator::PATH_VERSION_V3)
            ->and($media->getCustomProperty(\App\Support\Media\DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toStartWith('documents/team-'.$this->team->getKey().'/');
    });

    it('ignores a tampered client-supplied exchange rate', function (): void {
        Notification::fake();

        livewire(ViewSupplierRequest::class, ['record' => $this->quote->getKey()])
            ->callAction('submit', data: [
                'item_prices' => [$this->quoteItem->getKey() => '100'],
                'currency_id' => $this->eur->getKey(),
                'exchange_rate' => '0.000001',
            ])
            ->assertHasNoActionErrors();

        expect($this->quote->refresh()->exchange_rate)->toBe('1.10000000');
    });

    it('blocks submission once the validity date has passed', function (): void {
        $expired = SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($this->supplier)
            ->withCurrency($this->usd)
            ->pending()
            ->sentToSupplier()
            ->create([
                'creator_id' => $this->admin->getKey(),
                'quoted_at' => now()->subDays(40),
                'valid_until' => now()->subDay(),
            ]);

        expect($this->portalUser->can('submit', $expired))->toBeFalse();

        livewire(ViewSupplierRequest::class, ['record' => $expired->getKey()])
            ->assertActionHidden('submit');
    });

    it('denies submit and decline on another supplier\'s quote', function (): void {
        $rival = Company::factory()->supplier()->for($this->team)->create(['name' => 'Rival Supplier Co']);
        $foreign = SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($rival)
            ->withCurrency($this->usd)
            ->pending()
            ->sentToSupplier()
            ->validFor(30)
            ->create(['creator_id' => $this->admin->getKey()]);

        expect($this->portalUser->can('submit', $foreign))->toBeFalse()
            ->and($this->portalUser->can('decline', $foreign))->toBeFalse()
            ->and($this->portalUser->can('view', $foreign))->toBeFalse();
    });
});

describe('Declining a quote', function (): void {
    it('stamps declined_at, keeps PENDING status, and notifies the team', function (): void {
        Notification::fake();

        livewire(ViewSupplierRequest::class, ['record' => $this->quote->getKey()])
            ->callAction('decline')
            ->assertHasNoActionErrors();

        $quote = $this->quote->refresh();

        expect($quote->declined_at)->not->toBeNull()
            ->and($quote->status)->toBe(SupplierQuoteStatus::PENDING)
            ->and(app(SupplierRequestStatusPresenter::class)->label($quote))->toBe('Declined');

        Notification::assertSentTo($this->admin, SupplierQuoteDeclinedNotification::class);
    });

    it('is skipped by the awaiting-quotes reminder job', function (): void {
        Notification::fake();

        $this->quote->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        $declined = SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($this->supplier)
            ->withCurrency($this->usd)
            ->pending()
            ->sentToSupplier()
            ->declined()
            ->validFor(30)
            ->create(['creator_id' => $this->admin->getKey()]);
        $declined->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        (new CheckAwaitingSupplierQuotesJob)->handle();

        Notification::assertSentTo(
            $this->admin,
            AwaitingSupplierQuoteNotification::class,
            fn (AwaitingSupplierQuoteNotification $notification): bool => $notification->quote->is($this->quote),
        );
        Notification::assertSentToTimes($this->admin, AwaitingSupplierQuoteNotification::class, 1);
    });

    it('never mutates a declined quote to EXPIRED — Declined wins over Expired', function (): void {
        $declined = SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($this->supplier)
            ->withCurrency($this->usd)
            ->pending()
            ->sentToSupplier()
            ->declined()
            ->create([
                'creator_id' => $this->admin->getKey(),
                'quoted_at' => now()->subDays(40),
                'valid_until' => now()->subDay(),
            ]);

        $expiring = SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($this->supplier)
            ->withCurrency($this->usd)
            ->pending()
            ->sentToSupplier()
            ->create([
                'creator_id' => $this->admin->getKey(),
                'quoted_at' => now()->subDays(40),
                'valid_until' => now()->subDay(),
            ]);

        $declined->checkAndUpdateExpiredStatus();
        $expiring->checkAndUpdateExpiredStatus();

        expect($declined->refresh()->status)->toBe(SupplierQuoteStatus::PENDING)
            ->and(app(SupplierRequestStatusPresenter::class)->label($declined))->toBe('Declined')
            ->and($expiring->refresh()->status)->toBe(SupplierQuoteStatus::EXPIRED)
            ->and(app(SupplierRequestStatusPresenter::class)->label($expiring))->toBe('Expired');
    });

    it('resets a decline when staff re-send the RFQ', function (): void {
        $declined = SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($this->supplier)
            ->withCurrency($this->usd)
            ->pending()
            ->sentToSupplier()
            ->declined()
            ->validFor(30)
            ->create([
                'creator_id' => $this->admin->getKey(),
                'submitted_via' => SupplierQuoteSubmissionMethod::Portal,
                'submitted_at' => now()->subDay(),
                'submitted_by_user_id' => $this->portalUser->getKey(),
            ]);

        $previousSentAt = $declined->sent_to_supplier_at;

        $this->travel(1)->minutes();

        app(StampSupplierQuoteSent::class)->execute($declined);

        $quote = $declined->refresh();

        expect($quote->declined_at)->toBeNull()
            ->and($quote->submitted_via)->toBe(SupplierQuoteSubmissionMethod::Internal)
            ->and($quote->submitted_at)->toBeNull()
            ->and($quote->submitted_by_user_id)->toBeNull()
            ->and($quote->sent_to_supplier_at->greaterThan($previousSentAt))->toBeTrue()
            ->and(app(SupplierRequestStatusPresenter::class)->label($quote))->toBe('Awaiting your quote');
    });
});

describe('Send to Suppliers stamps the visibility gate', function (): void {
    beforeEach(function (): void {
        // Switch from the supplier-portal context (outer beforeEach) to the
        // internal admin context before touching guard-sensitive role APIs.
        $this->actingAs($this->admin, 'web');
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->team);

        $this->artisan('db:seed', ['--class' => 'ErpPermissionSeeder']);
        $this->admin->assignRole(\Spatie\Permission\Models\Role::findByName('admin', 'web'));
        $this->admin->markEmailAsVerified();

        $this->article = Article::factory()->for($this->team)->create(['name' => 'Gate Valve DN50']);

        SupplierArticle::factory()->create([
            'article_id' => $this->article->getKey(),
            'supplier_id' => $this->supplier->getKey(),
        ]);

        RequestItem::factory()->create([
            'request_id' => $this->request->getKey(),
            'article_id' => $this->article->getKey(),
            'description' => 'Gate Valve DN50',
            'quantity' => '5',
            'unit' => 'pcs',
            'notes' => null,
        ]);
    });

    it('stamps sent_to_supplier_at when the solicitation mail is dispatched', function (): void {
        Mail::fake();

        // The pre-existing quote from the outer beforeEach belongs to this
        // request+supplier pair; remove it so the send path creates fresh.
        $this->quote->items()->delete();
        $this->quote->forceDelete();

        livewire(ItemsRelationManager::class, [
            'ownerRecord' => $this->request,
            'pageClass' => EditRequest::class,
        ])
            ->callAction(TestAction::make('sendRequestToAllSuppliers')->table())
            ->assertHasNoActionErrors();

        $quote = SupplierQuote::query()
            ->where('request_id', $this->request->getKey())
            ->where('supplier_id', $this->supplier->getKey())
            ->firstOrFail();

        expect($quote->sent_to_supplier_at)->not->toBeNull();

        Mail::assertSent(QuoteToSupplierMail::class);
    });

    it('re-sends to a declined supplier, clearing the decline and re-stamping the gate', function (): void {
        Mail::fake();

        $this->quote->forceFill([
            'declined_at' => now()->subDay(),
            'sent_to_supplier_at' => now()->subDays(2),
            'submitted_via' => SupplierQuoteSubmissionMethod::Portal,
            'submitted_at' => now()->subDay(),
            'submitted_by_user_id' => $this->portalUser->getKey(),
            'notification_metadata' => [
                'email_sent' => true,
                'email_sent_at' => now()->subDays(2)->toIso8601String(),
                'email_error' => null,
            ],
        ])->saveQuietly();

        livewire(ItemsRelationManager::class, [
            'ownerRecord' => $this->request,
            'pageClass' => EditRequest::class,
        ])
            ->callAction(TestAction::make('sendRequestToAllSuppliers')->table())
            ->assertHasNoActionErrors();

        $quote = $this->quote->refresh();

        expect($quote->declined_at)->toBeNull()
            ->and($quote->submitted_at)->toBeNull()
            ->and($quote->submitted_by_user_id)->toBeNull()
            ->and($quote->sent_to_supplier_at->greaterThan(now()->subHour()))->toBeTrue();

        Mail::assertSent(QuoteToSupplierMail::class);
    });

    it('embeds a supplier-portal deep link in the solicitation mail', function (): void {
        $rendered = (new QuoteToSupplierMail($this->quote))->render();

        expect($rendered)->toContain(url()->getSupplierPortalUrl('requests'));
    });
});

describe('Confidentiality', function (): void {
    it('renders no buyer identity, request context, internal notes, or other suppliers', function (): void {
        $rival = Company::factory()->supplier()->for($this->team)->create(['name' => 'Rival Supplier Co']);
        SupplierQuote::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forSupplier($rival)
            ->withCurrency($this->usd)
            ->pending()
            ->sentToSupplier()
            ->validFor(30)
            ->create(['creator_id' => $this->admin->getKey()]);

        livewire(ViewSupplierRequest::class, ['record' => $this->quote->getKey()])
            ->assertSee($this->quote->quote_number)
            ->assertSee('Steel Pipe 5m')
            ->assertDontSee('Confidential Buyer Ltd')
            ->assertDontSee('Confidential Sourcing Project')
            ->assertDontSee($this->request->request_number)
            ->assertDontSee('INTERNAL-EYES-ONLY')
            ->assertDontSee('Rival Supplier Co');

        livewire(ListSupplierRequests::class)
            ->assertSee($this->quote->quote_number)
            ->assertDontSee('Confidential Buyer Ltd')
            ->assertDontSee($this->request->request_number)
            ->assertDontSee('Rival Supplier Co');
    });

    it('never selects internal notes or notification metadata into portal records', function (): void {
        $record = \App\Filament\Supplier\Resources\SupplierRequestResource::getEloquentQuery()
            ->whereKey($this->quote->getKey())
            ->firstOrFail();

        expect($record->getAttributes())
            ->not->toHaveKey('internal_notes')
            ->not->toHaveKey('notification_metadata')
            ->not->toHaveKey('request_id');
    });
});
