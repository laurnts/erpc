<?php

declare(strict_types=1);

use App\Enums\CentralPurchasingRole;
use App\Enums\CreditLimitRequestStatus;
use App\Mail\Erp\CreditLimitIncreaseRequestMail;
use App\Models\BuyerCreditLimitRequest;
use App\Models\Company;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use App\Services\Email\EmailTemplateService;
use App\Services\TeamMemberService;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();

    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'credit_limit' => '1000.00',
        'available_credit' => '1000.00',
    ]);

    $this->requester = User::factory()->recycle($this->team)->create();
    $this->financeApprover1 = User::factory()->create(['email' => 'approver1@test.com']);
    $this->financeApprover2 = User::factory()->create(['email' => 'approver2@test.com']);

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

    $this->actingAs($this->requester);
});

test('full workflow: request creation → email → approve (2x) → credit limit updated', function (): void {
    // Step 1: Create request
    $request = BuyerCreditLimitRequest::factory()->create([
        'team_id' => $this->team->getKey(),
        'buyer_id' => $this->buyer->getKey(),
        'current_limit' => '1000.00',
        'requested_limit' => '2000.00',
        'requested_by_id' => $this->requester->getKey(),
    ]);

    $this->buyer->requested_credit_limit = '2000.00';
    $this->buyer->save();

    // Step 2: Send email notifications
    $approvers = TeamMemberService::getFinanceApprovers($this->team);
    $emailService = app(EmailTemplateService::class);

    foreach ($approvers as $approver) {
        $emailService->sendWithTeamSettings(
            $this->team,
            new CreditLimitIncreaseRequestMail($request),
            $approver->email
        );
    }

    Mail::assertSent(CreditLimitIncreaseRequestMail::class, 2);

    // Step 3: First approval
    $this->actingAs($this->financeApprover1);
    $request->approve($this->financeApprover1, 'First approval');

    expect($request->fresh()->approvalCount())->toBe(1)
        ->and($request->fresh()->status)->toBe(CreditLimitRequestStatus::PENDING)
        ->and($this->buyer->fresh()->credit_limit)->toBe('1000.00'); // Not updated yet

    // Step 4: Second approval
    $this->actingAs($this->financeApprover2);
    $request->approve($this->financeApprover2, 'Second approval');

    // Step 5: Verify credit limit updated
    $this->buyer->refresh();
    expect($request->fresh()->status)->toBe(CreditLimitRequestStatus::APPROVED)
        ->and($this->buyer->credit_limit)->toBe('2000.00')
        ->and($this->buyer->derived_available_credit)->toBe(2000.0)
        ->and($this->buyer->requested_credit_limit)->toBeNull();
});

test('race condition prevention: concurrent approvals', function (): void {
    $request = BuyerCreditLimitRequest::factory()->create([
        'team_id' => $this->team->getKey(),
        'buyer_id' => $this->buyer->getKey(),
        'current_limit' => '1000.00',
        'requested_limit' => '2000.00',
    ]);

    // Simulate concurrent approvals
    $this->actingAs($this->financeApprover1);
    $request1 = BuyerCreditLimitRequest::find($request->getKey());
    $request1->approve($this->financeApprover1);

    $this->actingAs($this->financeApprover2);
    $request2 = BuyerCreditLimitRequest::find($request->getKey());
    $request2->approve($this->financeApprover2);

    // Should only have 2 approvals total
    expect($request->fresh()->approvalCount())->toBe(2)
        ->and($request->fresh()->status)->toBe(CreditLimitRequestStatus::APPROVED);
});

test('rejection clears requested limit', function (): void {
    $request = BuyerCreditLimitRequest::factory()->create([
        'team_id' => $this->team->getKey(),
        'buyer_id' => $this->buyer->getKey(),
        'current_limit' => '1000.00',
        'requested_limit' => '2000.00',
    ]);

    $this->buyer->requested_credit_limit = '2000.00';
    $this->buyer->save();

    $this->actingAs($this->financeApprover1);
    $request->reject($this->financeApprover1, 'Insufficient justification');

    expect($this->buyer->fresh()->requested_credit_limit)->toBeNull()
        ->and($this->buyer->fresh()->credit_limit)->toBe('1000.00') // Unchanged
        ->and($this->buyer->fresh()->available_credit)->toBe('1000.00'); // Unchanged
});

test('approver designation workflow', function (): void {
    $nonApproverFinance = User::factory()->create();
    Membership::factory()->create([
        'team_id' => $this->team->getKey(),
        'user_id' => $nonApproverFinance->getKey(),
        'role' => 'central_purchasing',
        'central_purchasing_role' => CentralPurchasingRole::FINANCE,
        'is_approver' => false,
    ]);

    $request = BuyerCreditLimitRequest::factory()->create([
        'team_id' => $this->team->getKey(),
        'buyer_id' => $this->buyer->getKey(),
    ]);

    // Non-approver cannot approve
    expect($request->canBeApprovedBy($nonApproverFinance))->toBeFalse();

    // Make them an approver
    $membership = Membership::where('team_id', $this->team->getKey())
        ->where('user_id', $nonApproverFinance->getKey())
        ->first();
    $membership->is_approver = true;
    $membership->save();

    // Now they can approve
    expect($request->canBeApprovedBy($nonApproverFinance))->toBeTrue();
});

test('email notification sent only to finance approvers', function (): void {
    $nonApproverFinance = User::factory()->create(['email' => 'nonapprover@test.com']);
    Membership::factory()->create([
        'team_id' => $this->team->getKey(),
        'user_id' => $nonApproverFinance->getKey(),
        'role' => 'central_purchasing',
        'central_purchasing_role' => CentralPurchasingRole::FINANCE,
        'is_approver' => false,
    ]);

    $request = BuyerCreditLimitRequest::factory()->create([
        'team_id' => $this->team->getKey(),
        'buyer_id' => $this->buyer->getKey(),
    ]);

    $approvers = TeamMemberService::getFinanceApprovers($this->team);
    $emailService = app(EmailTemplateService::class);

    foreach ($approvers as $approver) {
        $emailService->sendWithTeamSettings(
            $this->team,
            new CreditLimitIncreaseRequestMail($request),
            $approver->email
        );
    }

    // Should only send to approvers, not non-approver finance users
    Mail::assertSent(CreditLimitIncreaseRequestMail::class, 2);
    Mail::assertNotSent(CreditLimitIncreaseRequestMail::class, function ($mail) {
        return $mail->hasTo('nonapprover@test.com');
    });
});
