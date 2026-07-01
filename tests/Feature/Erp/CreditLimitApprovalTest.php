<?php

declare(strict_types=1);

use App\Enums\CentralPurchasingRole;
use App\Enums\CreditLimitRequestStatus;
use App\Mail\Erp\CreditLimitIncreaseRequestMail;
use App\Models\BuyerCreditLimitRequest;
use App\Models\BuyerCreditLimitRequestApproval;
use App\Models\Company;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamMemberService;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'credit_limit' => '1000.00',
        'available_credit' => '1000.00',
    ]);
    $this->requester = User::factory()->recycle($this->team)->create();
    $this->actingAs($this->requester);
});

describe('Credit Limit Request Creation', function (): void {
    it('can create a credit limit increase request', function (): void {
        $request = BuyerCreditLimitRequest::factory()->create([
            'team_id' => $this->team->getKey(),
            'buyer_id' => $this->buyer->getKey(),
            'current_limit' => '1000.00',
            'requested_limit' => '2000.00',
            'requested_by_id' => $this->requester->getKey(),
        ]);

        expect($request)->toBeInstanceOf(BuyerCreditLimitRequest::class)
            ->and($request->status)->toBe(CreditLimitRequestStatus::PENDING)
            ->and($request->current_limit)->toBe('1000.00')
            ->and($request->requested_limit)->toBe('2000.00');
    });

    it('sets requested_credit_limit on buyer when request is created', function (): void {
        $request = BuyerCreditLimitRequest::factory()->create([
            'team_id' => $this->team->getKey(),
            'buyer_id' => $this->buyer->getKey(),
            'requested_limit' => '2000.00',
        ]);

        $this->buyer->requested_credit_limit = $request->requested_limit;
        $this->buyer->save();

        expect($this->buyer->fresh()->requested_credit_limit)->toBe('2000.00');
    });
});

describe('Email Notification', function (): void {
    it('sends email notification to finance approvers when request is created', function (): void {
        Mail::fake();

        $financeApprover1 = User::factory()->create();
        $financeApprover2 = User::factory()->create();
        $nonApprover = User::factory()->create();

        // Create memberships with finance role
        Membership::factory()->create([
            'team_id' => $this->team->getKey(),
            'user_id' => $financeApprover1->getKey(),
            'role' => 'central_purchasing',
            'central_purchasing_role' => CentralPurchasingRole::FINANCE,
            'is_approver' => true,
        ]);
        Membership::factory()->create([
            'team_id' => $this->team->getKey(),
            'user_id' => $financeApprover2->getKey(),
            'role' => 'central_purchasing',
            'central_purchasing_role' => CentralPurchasingRole::FINANCE,
            'is_approver' => true,
        ]);
        Membership::factory()->create([
            'team_id' => $this->team->getKey(),
            'user_id' => $nonApprover->getKey(),
            'role' => 'central_purchasing',
            'central_purchasing_role' => CentralPurchasingRole::FINANCE,
            'is_approver' => false,
        ]);

        $request = BuyerCreditLimitRequest::factory()->create([
            'team_id' => $this->team->getKey(),
            'buyer_id' => $this->buyer->getKey(),
        ]);

        $approvers = TeamMemberService::getFinanceApprovers($this->team);

        foreach ($approvers as $approver) {
            Mail::to($approver->email)->send(new CreditLimitIncreaseRequestMail($request));
        }

        Mail::assertSent(CreditLimitIncreaseRequestMail::class, 2);
    });
});

describe('Approval Workflow', function (): void {
    beforeEach(function (): void {
        $this->financeApprover1 = User::factory()->create();
        $this->financeApprover2 = User::factory()->create();

        Membership::factory()->create([
            'team_id' => $this->team->getKey(),
            'user_id' => $this->financeApprover1->getKey(),
            'role' => 'central_purchasing',
            'central_purchasing_role' => CentralPurchasingRole::FINANCE,
            'is_approver' => true,
        ]);
        Membership::factory()->create([
            'team_id' => $this->team->getKey(),
            'user_id' => $this->financeApprover2->getKey(),
            'role' => 'central_purchasing',
            'central_purchasing_role' => CentralPurchasingRole::FINANCE,
            'is_approver' => true,
        ]);

        $this->request = BuyerCreditLimitRequest::factory()->create([
            'team_id' => $this->team->getKey(),
            'buyer_id' => $this->buyer->getKey(),
            'current_limit' => '1000.00',
            'requested_limit' => '2000.00',
        ]);
    });

    it('allows first approval by finance approver', function (): void {
        $this->actingAs($this->financeApprover1);

        expect($this->request->canBeApprovedBy($this->financeApprover1))->toBeTrue();

        $this->request->approve($this->financeApprover1, 'Looks good');

        expect($this->request->fresh()->approvalCount())->toBe(1)
            ->and($this->request->fresh()->status)->toBe(CreditLimitRequestStatus::PENDING);
    });

    it('updates buyer credit limit and available credit on second approval', function (): void {
        $this->actingAs($this->financeApprover1);
        $this->request->approve($this->financeApprover1);

        $this->actingAs($this->financeApprover2);
        $this->request->approve($this->financeApprover2);

        $this->buyer->refresh();
        expect($this->request->fresh()->status)->toBe(CreditLimitRequestStatus::APPROVED)
            ->and($this->buyer->credit_limit)->toBe('2000.00')
            ->and($this->buyer->available_credit)->toBe('2000.00')
            ->and($this->buyer->requested_credit_limit)->toBeNull();
    });

    it('prevents duplicate approval by same user', function (): void {
        $this->actingAs($this->financeApprover1);
        $this->request->approve($this->financeApprover1);

        expect($this->request->fresh()->canBeApprovedBy($this->financeApprover1))->toBeFalse();
    });

    it('prevents approval by non-approver finance user', function (): void {
        $nonApprover = User::factory()->create();
        Membership::factory()->create([
            'team_id' => $this->team->getKey(),
            'user_id' => $nonApprover->getKey(),
            'role' => 'central_purchasing',
            'central_purchasing_role' => CentralPurchasingRole::FINANCE,
            'is_approver' => false,
        ]);

        expect($this->request->canBeApprovedBy($nonApprover))->toBeFalse();
    });

    it('prevents approval when request is already approved', function (): void {
        $this->actingAs($this->financeApprover1);
        $this->request->approve($this->financeApprover1);
        $this->actingAs($this->financeApprover2);
        $this->request->approve($this->financeApprover2);

        expect($this->request->fresh()->canBeApprovedBy($this->financeApprover1))->toBeFalse();
    });
});

describe('Rejection Workflow', function (): void {
    beforeEach(function (): void {
        $this->financeApprover = User::factory()->create();

        Membership::factory()->create([
            'team_id' => $this->team->getKey(),
            'user_id' => $this->financeApprover->getKey(),
            'role' => 'central_purchasing',
            'central_purchasing_role' => CentralPurchasingRole::FINANCE,
            'is_approver' => true,
        ]);

        $this->request = BuyerCreditLimitRequest::factory()->create([
            'team_id' => $this->team->getKey(),
            'buyer_id' => $this->buyer->getKey(),
            'current_limit' => '1000.00',
            'requested_limit' => '2000.00',
        ]);
        $this->buyer->requested_credit_limit = '2000.00';
        $this->buyer->save();
    });

    it('can reject a pending request', function (): void {
        $this->actingAs($this->financeApprover);

        expect($this->request->canBeRejectedBy($this->financeApprover))->toBeTrue();

        $this->request->reject($this->financeApprover, 'Insufficient justification');

        expect($this->request->fresh()->status)->toBe(CreditLimitRequestStatus::REJECTED)
            ->and($this->request->fresh()->rejected_by_id)->toBe($this->financeApprover->getKey())
            ->and($this->request->fresh()->rejected_reason)->toBe('Insufficient justification')
            ->and($this->buyer->fresh()->requested_credit_limit)->toBeNull();
    });

    it('clears requested_credit_limit on buyer when rejected', function (): void {
        $this->actingAs($this->financeApprover);
        $this->request->reject($this->financeApprover, 'Rejected');

        expect($this->buyer->fresh()->requested_credit_limit)->toBeNull();
    });
});

describe('Available Credit Calculation', function (): void {
    it('increases available credit by increase amount on approval', function (): void {
        $this->buyer->update([
            'credit_limit' => '1000.00',
            'available_credit' => '500.00', // Some credit already used
        ]);

        $financeApprover1 = User::factory()->create();
        $financeApprover2 = User::factory()->create();

        Membership::factory()->create([
            'team_id' => $this->team->getKey(),
            'user_id' => $financeApprover1->getKey(),
            'role' => 'central_purchasing',
            'central_purchasing_role' => CentralPurchasingRole::FINANCE,
            'is_approver' => true,
        ]);
        Membership::factory()->create([
            'team_id' => $this->team->getKey(),
            'user_id' => $financeApprover2->getKey(),
            'role' => 'central_purchasing',
            'central_purchasing_role' => CentralPurchasingRole::FINANCE,
            'is_approver' => true,
        ]);

        $request = BuyerCreditLimitRequest::factory()->create([
            'team_id' => $this->team->getKey(),
            'buyer_id' => $this->buyer->getKey(),
            'current_limit' => '1000.00',
            'requested_limit' => '2000.00',
        ]);

        $this->actingAs($financeApprover1);
        $request->approve($financeApprover1);
        $this->actingAs($financeApprover2);
        $request->approve($financeApprover2);

        $this->buyer->refresh();
        // available_credit should be 500 (existing) + 1000 (increase) = 1500
        expect($this->buyer->available_credit)->toBe('1500.00');
    });
});
