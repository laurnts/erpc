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
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->user->assignRole('superadmin');
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    $this->buyer = Company::factory()->buyer()->for($this->team)->create([
        'credit_limit' => '1000.00',
    ]);
});

it('creates a pending credit limit request and notifies finance approvers', function (): void {
    Mail::fake();

    $approver = User::factory()->create(['email' => 'finance@team.test']);

    Membership::factory()->create([
        'team_id' => $this->team->getKey(),
        'user_id' => $approver->getKey(),
        'role' => 'central_purchasing',
        'central_purchasing_role' => CentralPurchasingRole::FINANCE,
        'is_approver' => true,
    ]);

    livewire(ListCreditLimitRequests::class)
        ->callAction('request_credit_increase', [
            'buyer_id' => $this->buyer->getKey(),
            'requested_limit' => '5000.00',
        ])
        ->assertNotified('Request Submitted');

    $request = BuyerCreditLimitRequest::query()
        ->where('buyer_id', $this->buyer->getKey())
        ->first();

    expect($request)->not->toBeNull()
        ->and($request->status)->toBe(CreditLimitRequestStatus::PENDING)
        ->and((float) $request->requested_limit)->toBe(5000.0)
        ->and((float) ($this->buyer->refresh()->requested_credit_limit ?? 0))->toBe(5000.0);
});

it('rejects a second request while one is already pending', function (): void {
    BuyerCreditLimitRequest::factory()->create([
        'team_id' => $this->team->getKey(),
        'buyer_id' => $this->buyer->getKey(),
        'current_limit' => '1000.00',
        'requested_limit' => '2000.00',
        'requested_by_id' => $this->user->getKey(),
    ]);

    livewire(ListCreditLimitRequests::class)
        ->callAction('request_credit_increase', [
            'buyer_id' => $this->buyer->getKey(),
            'requested_limit' => '9000.00',
        ])
        ->assertNotified('Request Already Exists');

    expect(BuyerCreditLimitRequest::query()->where('buyer_id', $this->buyer->getKey())->count())->toBe(1);
});
