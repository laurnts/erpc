<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'credit_limit' => '1000.00',
    ]);
    $this->actingAs(User::factory()->recycle($this->team)->create());
});

it('blocks a direct credit_limit change made outside the approval workflow', function (): void {
    expect(fn (): bool => $this->buyer->update(['credit_limit' => '5000.00']))
        ->toThrow(RuntimeException::class);

    expect($this->buyer->fresh()->credit_limit)->toBe('1000.00');
});

it('blocks a direct credit_limit attribute assignment and save', function (): void {
    $this->buyer->credit_limit = '5000.00';

    expect(fn (): bool => $this->buyer->save())
        ->toThrow(RuntimeException::class);

    expect($this->buyer->fresh()->credit_limit)->toBe('1000.00');
});

it('allows a credit_limit change inside an authorized scope', function (): void {
    Company::withAuthorizedCreditLimitChange(function (): void {
        $this->buyer->update(['credit_limit' => '5000.00']);
    });

    expect($this->buyer->fresh()->credit_limit)->toBe('5000.00');
});

it('re-locks the credit_limit after the authorized scope closes', function (): void {
    Company::withAuthorizedCreditLimitChange(function (): void {
        $this->buyer->update(['credit_limit' => '5000.00']);
    });

    expect(Company::creditLimitChangeAuthorized())->toBeFalse()
        ->and(fn (): bool => $this->buyer->update(['credit_limit' => '9000.00']))
        ->toThrow(RuntimeException::class);
});

it('does not block updates to other company fields', function (): void {
    $this->buyer->update(['name' => 'Renamed Buyer', 'is_on_hold' => true]);

    expect($this->buyer->fresh()->name)->toBe('Renamed Buyer')
        ->and($this->buyer->fresh()->is_on_hold)->toBeTrue();
});

it('does not block updates that leave the credit_limit unchanged', function (): void {
    $this->buyer->update(['credit_limit' => '1000.00', 'requested_credit_limit' => '750.00']);

    expect($this->buyer->fresh()->requested_credit_limit)->toBe('750.00');
});

it('allows the initial credit_limit to be set at creation', function (): void {
    $buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'credit_limit' => '2500.00',
    ]);

    expect($buyer->fresh()->credit_limit)->toBe('2500.00');
});
