<?php

declare(strict_types=1);

use App\Enums\CentralPurchasingRole;
use App\Enums\CreditLimitRequestStatus;
use App\Filament\Resources\BuyerCreditLimitRequestResource\Pages\ListCreditLimitRequests;
use App\Models\BuyerCreditLimitRequest;
use App\Models\Company;
use App\Models\Membership;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    $this->buyer = Company::factory()->buyer()->for($this->team)->create([
        'credit_limit' => '1000.00',
        'available_credit' => '1000.00',
    ]);
});

/**
 * Grant finance-approver membership on the acting team to the given user.
 */
function creditLimitGrantFinanceApprover(Tests\TestCase $test, ?User $user = null, bool $isApprover = true): User
{
    $user ??= User::factory()->create();

    Membership::factory()->create([
        'team_id' => $test->team->getKey(),
        'user_id' => $user->getKey(),
        'role' => 'central_purchasing',
        'central_purchasing_role' => CentralPurchasingRole::FINANCE,
        'is_approver' => $isApprover,
    ]);

    return $user;
}

/**
 * Create a pending credit limit request for the acting team's buyer.
 */
function creditLimitPendingRequest(Tests\TestCase $test): BuyerCreditLimitRequest
{
    return BuyerCreditLimitRequest::factory()->create([
        'team_id' => $test->team->getKey(),
        'buyer_id' => $test->buyer->getKey(),
        'current_limit' => '1000.00',
        'requested_limit' => '2000.00',
        'requested_by_id' => $test->user->getKey(),
    ]);
}

describe('Approve action happy path', function (): void {
    it('records the first approval without touching the buyer credit limit', function (): void {
        creditLimitGrantFinanceApprover($this, $this->user);
        $record = creditLimitPendingRequest($this);

        livewire(ListCreditLimitRequests::class)
            ->assertOk()
            ->assertTableActionVisible('approve', $record)
            ->callTableAction('approve', $record, data: ['notes' => 'Looks good'])
            ->assertNotified('Request Approved');

        $record->refresh();
        expect($record->approvalCount())->toBe(1)
            ->and($record->status)->toBe(CreditLimitRequestStatus::PENDING)
            ->and($record->approvals()->first()->notes)->toBe('Looks good')
            ->and($record->approvals()->first()->user_id)->toBe($this->user->getKey())
            ->and($this->buyer->fresh()->credit_limit)->toBe('1000.00');
    });

    it('updates the buyer credit limit when the action provides the second approval', function (): void {
        creditLimitGrantFinanceApprover($this, $this->user);
        $otherApprover = creditLimitGrantFinanceApprover($this);
        $record = creditLimitPendingRequest($this);
        $this->buyer->update(['requested_credit_limit' => '2000.00']);

        $record->approve($otherApprover, 'First approval');

        livewire(ListCreditLimitRequests::class)
            ->assertOk()
            ->callTableAction('approve', $record, data: ['notes' => 'Second approval'])
            ->assertNotified('Request Approved');

        $this->buyer->refresh();
        expect($record->fresh()->status)->toBe(CreditLimitRequestStatus::APPROVED)
            ->and($record->fresh()->approvalCount())->toBe(2)
            ->and($this->buyer->credit_limit)->toBe('2000.00')
            ->and($this->buyer->derived_available_credit)->toBe(2000.0)
            ->and($this->buyer->requested_credit_limit)->toBeNull();
    });
});

describe('Reject action happy path', function (): void {
    it('rejects a pending request and clears the requested limit on the buyer', function (): void {
        creditLimitGrantFinanceApprover($this, $this->user);
        $record = creditLimitPendingRequest($this);
        $this->buyer->update(['requested_credit_limit' => '2000.00']);

        livewire(ListCreditLimitRequests::class)
            ->assertOk()
            ->assertTableActionVisible('reject', $record)
            ->callTableAction('reject', $record, data: ['reason' => 'Insufficient justification'])
            ->assertNotified('Request Rejected');

        $record->refresh();
        $this->buyer->refresh();
        expect($record->status)->toBe(CreditLimitRequestStatus::REJECTED)
            ->and($record->rejected_by_id)->toBe($this->user->getKey())
            ->and($record->rejected_reason)->toBe('Insufficient justification')
            ->and($record->rejected_at)->not->toBeNull()
            ->and($this->buyer->credit_limit)->toBe('1000.00')
            ->and($this->buyer->available_credit)->toBe('1000.00')
            ->and($this->buyer->requested_credit_limit)->toBeNull();
    });

    it('requires a rejection reason', function (): void {
        creditLimitGrantFinanceApprover($this, $this->user);
        $record = creditLimitPendingRequest($this);

        livewire(ListCreditLimitRequests::class)
            ->assertOk()
            ->callTableAction('reject', $record, data: ['reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        expect($record->fresh()->status)->toBe(CreditLimitRequestStatus::PENDING);
    });
});

describe('Approver gating', function (): void {
    it('hides approve and reject from a user without a finance approver membership', function (): void {
        $record = creditLimitPendingRequest($this);

        livewire(ListCreditLimitRequests::class)
            ->assertOk()
            ->assertTableActionHidden('approve', $record)
            ->assertTableActionHidden('reject', $record);
    });

    it('hides approve and reject from a finance member who is not designated approver', function (): void {
        creditLimitGrantFinanceApprover($this, $this->user, isApprover: false);
        $record = creditLimitPendingRequest($this);

        livewire(ListCreditLimitRequests::class)
            ->assertOk()
            ->assertTableActionHidden('approve', $record)
            ->assertTableActionHidden('reject', $record);
    });
});

describe('Double approval and post-decision guards', function (): void {
    it('hides approve from an approver who already approved the request', function (): void {
        creditLimitGrantFinanceApprover($this, $this->user);
        $record = creditLimitPendingRequest($this);

        $record->approve($this->user, 'First approval');

        livewire(ListCreditLimitRequests::class)
            ->assertOk()
            ->assertTableActionHidden('approve', $record)
            ->assertTableActionVisible('reject', $record);

        expect($record->fresh()->approvalCount())->toBe(1);
    });

    it('hides approve and reject once the request is fully approved', function (): void {
        creditLimitGrantFinanceApprover($this, $this->user);
        $approverOne = creditLimitGrantFinanceApprover($this);
        $approverTwo = creditLimitGrantFinanceApprover($this);
        $record = creditLimitPendingRequest($this);

        $record->approve($approverOne);
        $record->approve($approverTwo);

        livewire(ListCreditLimitRequests::class)
            ->assertOk()
            ->assertTableActionHidden('approve', $record)
            ->assertTableActionHidden('reject', $record);

        expect($record->fresh()->status)->toBe(CreditLimitRequestStatus::APPROVED)
            ->and($this->buyer->fresh()->credit_limit)->toBe('2000.00');
    });

    it('hides approve and reject after the request has been rejected', function (): void {
        creditLimitGrantFinanceApprover($this, $this->user);
        $record = creditLimitPendingRequest($this);

        $record->reject($this->user, 'Not now');

        livewire(ListCreditLimitRequests::class)
            ->assertOk()
            ->assertTableActionHidden('approve', $record)
            ->assertTableActionHidden('reject', $record);

        expect($this->buyer->fresh()->credit_limit)->toBe('1000.00');
    });
});
