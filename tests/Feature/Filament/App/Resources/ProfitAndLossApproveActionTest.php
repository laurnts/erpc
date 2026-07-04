<?php

declare(strict_types=1);

use App\Enums\CentralPurchasingRole;
use App\Enums\PNLStatus;
use App\Filament\Resources\ProfitAndLossResource\Pages\ViewProfitAndLoss;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Membership;
use App\Models\ProfitAndLoss;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
    $this->requestItem = RequestItem::factory()->recycle($this->request)->create(['parent_id' => null]);

    $this->quote = BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->forRequest($this->request)
        ->withCurrency($this->currency)
        ->create();

    // Net sell 1,000, + Tax 10% => gross 1,100; cost 600 => net margin 400.
    BuyerQuoteItem::factory()->forBuyerQuote($this->quote)->create([
        'request_item_id' => $this->requestItem->getKey(),
        'quantity' => '1', 'unit_price' => '1000', 'cost_price' => '600',
        'tax_rate' => '10', 'is_tax_inclusive' => true,
    ]);
    $this->quote->recalculateTotals();
});

/**
 * Create a non-owner team member holding the given Central Purchasing sub-role.
 */
function pnlApprovalMember(Tests\TestCase $test, CentralPurchasingRole $role): User
{
    $user = User::factory()->withPersonalTeam()->create();

    Membership::factory()->create([
        'team_id' => $test->team->getKey(),
        'user_id' => $user->getKey(),
        'role' => 'central_purchasing',
        'central_purchasing_role' => $role,
    ]);

    return $user;
}

/**
 * Create a PNL (status defaults to Need Approval) for the acting team's quote.
 *
 * @param  array<string, mixed>  $attributes
 */
function pnlNeedingApproval(Tests\TestCase $test, array $attributes = []): ProfitAndLoss
{
    return ProfitAndLoss::factory()->forBuyerQuote($test->quote)->create($attributes);
}

describe('Approve action happy path', function (): void {
    it('approves, stamps the director and freezes the financial snapshot when the sole assigned approver approves', function (): void {
        $director = pnlApprovalMember($this, CentralPurchasingRole::DIRECTOR);
        $record = pnlNeedingApproval($this, ['approved_by_id' => $director->getKey()]);

        $this->actingAs($director);

        livewire(ViewProfitAndLoss::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertActionVisible('approve')
            ->callAction('approve')
            ->assertNotified('Profit & Loss approved');

        $record->refresh();
        expect($record->status)->toBe(PNLStatus::APPROVED)
            ->and($record->director_approved_at)->not->toBeNull()
            ->and($record->dept_head_sales_approved_at)->toBeNull()
            ->and($record->deputy_director_approved_at)->toBeNull()
            ->and($record->financial_snapshot)->not->toBeNull();

        $snapshot = $record->financialSnapshotData();
        expect($snapshot->subtotal)->toBe(1000.0)
            ->and($snapshot->costTotal)->toBe(600.0)
            ->and($snapshot->marginAmount)->toBe(400.0)
            ->and($snapshot->grandTotal)->toBe(1100.0)
            ->and($snapshot->buyerQuoteId)->toBe($this->quote->getKey())
            ->and($snapshot->supplierGroups)->not->toBeEmpty();
    });

    it('records a partial approval without approving or snapshotting while other approvers are pending', function (): void {
        $deptHead = pnlApprovalMember($this, CentralPurchasingRole::DEPT_HEAD_SALES);
        $director = pnlApprovalMember($this, CentralPurchasingRole::DIRECTOR);
        $record = pnlNeedingApproval($this, [
            'dept_head_sales_id' => $deptHead->getKey(),
            'approved_by_id' => $director->getKey(),
        ]);

        $this->actingAs($deptHead);

        livewire(ViewProfitAndLoss::class, ['record' => $record->getKey()])
            ->assertOk()
            ->callAction('approve')
            ->assertNotified('Profit & Loss approved');

        $record->refresh();
        expect($record->dept_head_sales_approved_at)->not->toBeNull()
            ->and($record->director_approved_at)->toBeNull()
            ->and($record->status)->toBe(PNLStatus::NEED_APPROVAL)
            ->and($record->financial_snapshot)->toBeNull();
    });
});

describe('Approver gating', function (): void {
    it('hides approve from a user who is not an assigned approver', function (): void {
        $director = pnlApprovalMember($this, CentralPurchasingRole::DIRECTOR);
        $record = pnlNeedingApproval($this, ['approved_by_id' => $director->getKey()]);

        livewire(ViewProfitAndLoss::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertActionHidden('approve');

        expect($record->fresh()->status)->toBe(PNLStatus::NEED_APPROVAL);
    });

    it('hides approve from an assigned approver whose central purchasing role does not match', function (): void {
        $keyAccount = pnlApprovalMember($this, CentralPurchasingRole::KEY_ACCOUNT);
        $record = pnlNeedingApproval($this, ['approved_by_id' => $keyAccount->getKey()]);

        $this->actingAs($keyAccount);

        livewire(ViewProfitAndLoss::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertActionHidden('approve');

        expect($record->fresh()->status)->toBe(PNLStatus::NEED_APPROVAL)
            ->and($record->fresh()->director_approved_at)->toBeNull();
    });
});

describe('Already approved guard', function (): void {
    it('hides approve once the PNL is fully approved', function (): void {
        $director = pnlApprovalMember($this, CentralPurchasingRole::DIRECTOR);
        $record = pnlNeedingApproval($this, ['approved_by_id' => $director->getKey()]);

        $record->approve($director);
        expect($record->fresh()->status)->toBe(PNLStatus::APPROVED);

        $this->actingAs($director);

        livewire(ViewProfitAndLoss::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertActionHidden('approve');

        expect($record->fresh()->status)->toBe(PNLStatus::APPROVED);
    });

    it('hides approve from an approver who already approved while another approval is still pending', function (): void {
        $deptHead = pnlApprovalMember($this, CentralPurchasingRole::DEPT_HEAD_SALES);
        $director = pnlApprovalMember($this, CentralPurchasingRole::DIRECTOR);
        $record = pnlNeedingApproval($this, [
            'dept_head_sales_id' => $deptHead->getKey(),
            'approved_by_id' => $director->getKey(),
        ]);

        $record->approve($deptHead);

        $this->actingAs($deptHead);

        livewire(ViewProfitAndLoss::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertActionHidden('approve');

        expect($record->fresh()->status)->toBe(PNLStatus::NEED_APPROVAL)
            ->and($record->fresh()->financial_snapshot)->toBeNull();
    });
});
