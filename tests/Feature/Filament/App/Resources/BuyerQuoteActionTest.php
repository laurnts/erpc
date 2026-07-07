<?php

declare(strict_types=1);

use App\Enums\BuyerQuoteStatus;
use App\Enums\PNLStatus;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerQuotesRelationManager;
use App\Mail\Erp\QuoteToBuyerMail;
use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProfitAndLoss;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;
use App\Support\Media\DocumentPathGenerator;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Mail::fake();
    Notification::fake();

    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'email' => 'buyer@example.com',
    ]);
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create();
    $this->currency = Currency::factory()->usd()->create();
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();

    // The stage-tab mount guard only opens the Buyer Quotes tab once the request
    // has an approved QE or an obtained + selected supplier quote.
    SupplierQuote::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->forSupplier($this->supplier)
        ->withCurrency($this->currency)
        ->selected()
        ->create(['obtained' => true]);

    $this->user->assignRole('admin');
    $this->team->users()->attach($this->user, ['role' => 'admin']);
    $this->user->markEmailAsVerified();
    $this->user->update(['current_team_id' => $this->team->getKey()]);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->team);
});

/**
 * Create a buyer quote for the acting team's request in the given status.
 */
function buyerQuoteActionQuote(Tests\TestCase $test, BuyerQuoteStatus $status): BuyerQuote
{
    return BuyerQuote::factory()
        ->recycle($test->team)
        ->recycle($test->request)
        ->recycle($test->buyer)
        ->recycle($test->currency)
        ->recycle($test->user)
        ->create([
            'status' => $status,
            'issued_at' => $status === BuyerQuoteStatus::DRAFT ? null : now(),
        ]);
}

/**
 * Create a PNL for the acting team's request in the given status.
 */
function buyerQuoteActionPnl(Tests\TestCase $test, PNLStatus $status = PNLStatus::APPROVED): ProfitAndLoss
{
    return ProfitAndLoss::factory()
        ->recycle($test->user)
        ->forRequest($test->request)
        ->create(['status' => $status]);
}

/**
 * Mount the Buyer Quotes relation manager for the acting team's request.
 */
function buyerQuoteActionRelationManager(Tests\TestCase $test): Testable
{
    return livewire(BuyerQuotesRelationManager::class, [
        'ownerRecord' => $test->request,
        'pageClass' => ViewRequest::class,
    ]);
}

describe('edit record action', function (): void {
    it('shows edit for draft quotes', function (): void {
        $quote = buyerQuoteActionQuote($this, BuyerQuoteStatus::DRAFT);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('edit')->table($quote))
            ->assertActionHidden(TestAction::make('view')->table($quote));
    });

    it('shows edit for sent quotes', function (): void {
        $quote = buyerQuoteActionQuote($this, BuyerQuoteStatus::SENT);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('edit')->table($quote))
            ->assertActionHidden(TestAction::make('view')->table($quote));
    });

    it('shows view instead of edit for terminal statuses', function (BuyerQuoteStatus $status): void {
        $quote = buyerQuoteActionQuote($this, $status);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('edit')->table($quote))
            ->assertActionVisible(TestAction::make('view')->table($quote));
    })->with([
        'accepted' => BuyerQuoteStatus::ACCEPTED,
        'rejected' => BuyerQuoteStatus::REJECTED,
        'expired' => BuyerQuoteStatus::EXPIRED,
        'superseded' => BuyerQuoteStatus::SUPERSEDED,
    ]);
});

describe('new version record action', function (): void {
    it('shows new version for sent quotes even without additional items', function (): void {
        $quote = buyerQuoteActionQuote($this, BuyerQuoteStatus::SENT);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('newVersion')->table($quote));
    });

    it('creates a new draft version and marks the sent quote as superseded', function (): void {
        $quote = buyerQuoteActionQuote($this, BuyerQuoteStatus::SENT);
        $pnl = buyerQuoteActionPnl($this);
        $pnl->update(['buyer_quote_id' => $quote->getKey(), 'status' => PNLStatus::APPROVED]);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->callAction(TestAction::make('newVersion')->table($quote))
            ->assertNotified('New version created');

        $quote->refresh();
        $newQuote = BuyerQuote::query()
            ->where('previous_version_id', $quote->getKey())
            ->first();

        expect($quote->status)->toBe(BuyerQuoteStatus::SUPERSEDED)
            ->and($newQuote)->not->toBeNull()
            ->and($newQuote->status)->toBe(BuyerQuoteStatus::DRAFT)
            ->and($newQuote->version)->toBe(2)
            ->and($pnl->fresh()->buyer_quote_id)->toBe($newQuote->getKey())
            ->and($pnl->fresh()->status)->toBe(PNLStatus::NEED_APPROVAL);
    });

    it('shows new version for rejected and expired quotes', function (BuyerQuoteStatus $status): void {
        $quote = buyerQuoteActionQuote($this, $status);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('newVersion')->table($quote));
    })->with([
        'rejected' => BuyerQuoteStatus::REJECTED,
        'expired' => BuyerQuoteStatus::EXPIRED,
    ]);

    it('hides new version for draft, accepted, and superseded quotes', function (BuyerQuoteStatus $status): void {
        $quote = buyerQuoteActionQuote($this, $status);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('newVersion')->table($quote));
    })->with([
        'draft' => BuyerQuoteStatus::DRAFT,
        'accepted' => BuyerQuoteStatus::ACCEPTED,
        'superseded' => BuyerQuoteStatus::SUPERSEDED,
    ]);
});

describe('send header action', function (): void {
    it('marks the draft quote as sent and emails the buyer when the latest PNL is approved', function (): void {
        $quote = buyerQuoteActionQuote($this, BuyerQuoteStatus::DRAFT);
        buyerQuoteActionPnl($this);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('send')->table())
            ->callAction(TestAction::make('send')->table())
            ->assertNotified('Quote sent');

        $quote->refresh();
        expect($quote->status)->toBe(BuyerQuoteStatus::SENT)
            ->and($quote->issued_at)->not->toBeNull();

        Mail::assertSent(QuoteToBuyerMail::class, fn (QuoteToBuyerMail $mail): bool => $mail->hasTo('buyer@example.com'));
    });

    it('marks the draft quote as sent without emailing when the buyer has no email address', function (): void {
        $this->buyer->update(['email' => null]);
        $quote = buyerQuoteActionQuote($this, BuyerQuoteStatus::DRAFT);
        buyerQuoteActionPnl($this);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->callAction(TestAction::make('send')->table())
            ->assertNotified('Quote sent');

        expect($quote->refresh()->status)->toBe(BuyerQuoteStatus::SENT);

        Mail::assertNotSent(QuoteToBuyerMail::class);
    });

    it('hides send when the quote is not in draft status', function (BuyerQuoteStatus $status): void {
        $quote = buyerQuoteActionQuote($this, $status);
        buyerQuoteActionPnl($this);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('send')->table());

        expect($quote->refresh()->status)->toBe($status);
    })->with([
        'sent' => BuyerQuoteStatus::SENT,
        'accepted' => BuyerQuoteStatus::ACCEPTED,
        'rejected' => BuyerQuoteStatus::REJECTED,
        'expired' => BuyerQuoteStatus::EXPIRED,
        'superseded' => BuyerQuoteStatus::SUPERSEDED,
    ]);

    it('shows send but warns when the latest PNL is not approved', function (): void {
        $quote = buyerQuoteActionQuote($this, BuyerQuoteStatus::DRAFT);
        buyerQuoteActionPnl($this, PNLStatus::NEED_APPROVAL);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('send')->table())
            ->callAction(TestAction::make('send')->table())
            ->assertNotified('PNL approval required');

        expect($quote->refresh()->status)->toBe(BuyerQuoteStatus::DRAFT)
            ->and($quote->issued_at)->toBeNull();
    });

    it('shows send but warns when the request has no PNL at all', function (): void {
        buyerQuoteActionQuote($this, BuyerQuoteStatus::DRAFT);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('send')->table())
            ->callAction(TestAction::make('send')->table())
            ->assertNotified('PNL required');
    });
});

describe('reject record action', function (): void {
    it('marks a sent quote as rejected', function (): void {
        $quote = buyerQuoteActionQuote($this, BuyerQuoteStatus::SENT);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('reject')->table($quote))
            ->callAction(TestAction::make('reject')->table($quote))
            ->assertNotified('Quote rejected');

        expect($quote->refresh()->status)->toBe(BuyerQuoteStatus::REJECTED);
    });

    it('hides reject for quotes that are not in sent status', function (BuyerQuoteStatus $status): void {
        $quote = buyerQuoteActionQuote($this, $status);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('reject')->table($quote));

        expect($quote->refresh()->status)->toBe($status);
    })->with([
        'draft' => BuyerQuoteStatus::DRAFT,
        'accepted' => BuyerQuoteStatus::ACCEPTED,
        'rejected' => BuyerQuoteStatus::REJECTED,
        'expired' => BuyerQuoteStatus::EXPIRED,
        'superseded' => BuyerQuoteStatus::SUPERSEDED,
    ]);
});

describe('resend record action', function (): void {
    it('resends the quote email to the buyer without changing the quote status', function (): void {
        $quote = buyerQuoteActionQuote($this, BuyerQuoteStatus::SENT);
        $issuedAt = $quote->issued_at;

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('resend')->table($quote))
            ->callAction(TestAction::make('resend')->table($quote))
            ->assertNotified('Email resent');

        $quote->refresh();
        expect($quote->status)->toBe(BuyerQuoteStatus::SENT)
            ->and($quote->issued_at?->toDateTimeString())->toBe($issuedAt?->toDateTimeString());

        Mail::assertSent(QuoteToBuyerMail::class, fn (QuoteToBuyerMail $mail): bool => $mail->hasTo('buyer@example.com'));
    });

    it('warns and sends nothing when the buyer has no email address', function (): void {
        $this->buyer->update(['email' => null]);
        $quote = buyerQuoteActionQuote($this, BuyerQuoteStatus::SENT);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->callAction(TestAction::make('resend')->table($quote))
            ->assertNotified('Cannot resend email');

        expect($quote->refresh()->status)->toBe(BuyerQuoteStatus::SENT);

        Mail::assertNotSent(QuoteToBuyerMail::class);
    });

    it('hides resend for quotes that are not in sent status', function (BuyerQuoteStatus $status): void {
        $quote = buyerQuoteActionQuote($this, $status);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('resend')->table($quote));

        Mail::assertNotSent(QuoteToBuyerMail::class);
    })->with([
        'draft' => BuyerQuoteStatus::DRAFT,
        'accepted' => BuyerQuoteStatus::ACCEPTED,
        'rejected' => BuyerQuoteStatus::REJECTED,
        'expired' => BuyerQuoteStatus::EXPIRED,
        'superseded' => BuyerQuoteStatus::SUPERSEDED,
    ]);
});

describe('uploadPo action', function (): void {
    it('attaches the uploaded PO file with a v3-stamped document path', function (): void {
        $quote = buyerQuoteActionQuote($this, BuyerQuoteStatus::SENT);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->callAction(TestAction::make('uploadPo')->table($quote), [
                'buyer_po_files' => [UploadedFile::fake()->createWithContent(
                    'po.pdf',
                    "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n",
                )],
            ])
            ->assertHasNoActionErrors();

        $media = $quote->refresh()->getFirstMedia('buyer_po');

        expect($media)->not->toBeNull()
            ->and($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
            ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toStartWith('documents/team-'.$this->team->getKey().'/');
    });
});

describe('permission gating', function (): void {
    it('hides send, resend, and reject from a team member with only view permission', function (): void {
        buyerQuoteActionQuote($this, BuyerQuoteStatus::DRAFT);
        $sentQuote = buyerQuoteActionQuote($this, BuyerQuoteStatus::SENT);
        buyerQuoteActionPnl($this);

        $member = User::factory()->create();
        $this->team->users()->attach($member, ['role' => 'editor']);
        $member->markEmailAsVerified();
        $member->update(['current_team_id' => $this->team->getKey()]);

        $this->actingAs($member);
        Filament::setTenant($this->team);

        buyerQuoteActionRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('send')->table())
            ->assertActionHidden(TestAction::make('resend')->table($sentQuote))
            ->assertActionHidden(TestAction::make('reject')->table($sentQuote));

        expect($sentQuote->refresh()->status)->toBe(BuyerQuoteStatus::SENT);
    });
});
