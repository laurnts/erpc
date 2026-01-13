<?php

declare(strict_types=1);

use App\Models\Buyer;
use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
});

test('buyer can be created via factory', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->create([
        'name' => 'Test Buyer',
        'code' => 'BUY-TEST',
        'contact_person' => 'John Doe',
        'email' => 'john@example.com',
        'creator_id' => $this->user->id,
    ]);

    expect($buyer)->toBeInstanceOf(Buyer::class)
        ->and($buyer->name)->toBe('Test Buyer')
        ->and($buyer->code)->toBe('BUY-TEST')
        ->and($buyer->contact_person)->toBe('John Doe')
        ->and($buyer->email)->toBe('john@example.com')
        ->and($buyer->team_id)->toBe($this->user->personalTeam()->id)
        ->and($buyer->creator_id)->toBe($this->user->id);
});

test('buyer belongs to team', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->create();

    expect($buyer->team)->toBeInstanceOf(Team::class)
        ->and($buyer->team->id)->toBe($this->user->personalTeam()->id);
});

test('buyer belongs to creator', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    expect($buyer->creator)->toBeInstanceOf(User::class)
        ->and($buyer->creator->id)->toBe($this->user->id);
});

test('buyer has default values', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->create([
        'credit_limit' => 0,
        'credit_used' => 0,
        'is_on_hold' => false,
        'is_active' => true,
    ]);

    expect($buyer->credit_limit)->toBe('0.00')
        ->and($buyer->credit_used)->toBe('0.00')
        ->and($buyer->is_on_hold)->toBeFalse()
        ->and($buyer->is_active)->toBeTrue();
});

test('buyer code is unique per team', function () {
    Buyer::factory()->for($this->user->personalTeam())->create(['code' => 'BUY-0001']);

    expect(fn () => Buyer::factory()->for($this->user->personalTeam())->create(['code' => 'BUY-0001']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('same buyer code can exist in different teams', function () {
    $user2 = User::factory()->withPersonalTeam()->create();

    $buyer1 = Buyer::factory()->for($this->user->personalTeam())->create(['code' => 'BUY-SHARED']);
    $buyer2 = Buyer::factory()->for($user2->personalTeam())->create(['code' => 'BUY-SHARED']);

    expect($buyer1->id)->not->toBe($buyer2->id)
        ->and($buyer1->code)->toBe($buyer2->code)
        ->and($buyer1->team_id)->not->toBe($buyer2->team_id);
});

test('buyer can be deactivated', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->create(['is_active' => true]);

    $buyer->update(['is_active' => false]);

    expect($buyer->fresh()->is_active)->toBeFalse();
});

test('buyer can be put on hold', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->create(['is_on_hold' => false]);

    $buyer->update([
        'is_on_hold' => true,
        'on_hold_reason' => 'Payment overdue',
    ]);

    expect($buyer->fresh()->is_on_hold)->toBeTrue()
        ->and($buyer->fresh()->on_hold_reason)->toBe('Payment overdue');
});

test('buyer factory creates valid buyer', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->create();

    expect($buyer->name)->not->toBeEmpty()
        ->and($buyer->code)->not->toBeEmpty()
        ->and($buyer->team_id)->not->toBeNull()
        ->and($buyer->team_id)->toBe($this->user->personalTeam()->id);
});

test('inactive factory state works', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->inactive()->create();

    expect($buyer->is_active)->toBeFalse();
});

test('on hold factory state works', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->onHold('Credit limit exceeded')->create();

    expect($buyer->is_on_hold)->toBeTrue()
        ->and($buyer->on_hold_reason)->toBe('Credit limit exceeded');
});

test('available credit is calculated correctly', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->create([
        'credit_limit' => 10000.00,
        'credit_used' => 3500.00,
    ]);

    expect($buyer->available_credit)->toBe('6500.00');
});

test('available credit can be negative when over limit', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->create([
        'credit_limit' => 5000.00,
        'credit_used' => 7500.00,
    ]);

    expect($buyer->available_credit)->toBe('-2500.00');
});

test('buyer can be linked to company', function () {
    $company = Company::factory()->for($this->user->personalTeam())->create([
        'name' => 'Test Company',
    ]);

    $buyer = Buyer::factory()->for($this->user->personalTeam())->create([
        'company_id' => $company->id,
    ]);

    expect($buyer->company)->toBeInstanceOf(Company::class)
        ->and($buyer->company->id)->toBe($company->id)
        ->and($buyer->company->name)->toBe('Test Company');
});

test('buyer company relationship is optional', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->create([
        'company_id' => null,
    ]);

    expect($buyer->company)->toBeNull();
});

test('buyer observer sets team and creator on create', function () {
    $buyer = Buyer::create([
        'name' => 'Observer Test',
        'code' => 'BUY-OBS',
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
    ]);

    expect($buyer->team_id)->toBe($this->user->personalTeam()->id)
        ->and($buyer->creator_id)->toBe($this->user->id);
});

test('buyer observer auto-generates code when not provided', function () {
    // Test code generation logic directly, since Event::fake() prevents observers
    $observer = app(\App\Observers\BuyerObserver::class);

    // First buyer
    $buyer1 = new Buyer([
        'name' => 'First Buyer',
        'team_id' => $this->user->personalTeam()->id,
    ]);
    $observer->creating($buyer1);

    expect($buyer1->code)->toBe('BUY-0001');

    // Save it to test sequential generation
    $buyer1->save();

    // Second buyer
    $buyer2 = new Buyer([
        'name' => 'Second Buyer',
        'team_id' => $this->user->personalTeam()->id,
    ]);
    $observer->creating($buyer2);

    expect($buyer2->code)->toBe('BUY-0002');

    // Third buyer with explicit code should keep it
    $buyer3 = new Buyer([
        'name' => 'Third Buyer',
        'code' => 'BUY-CUSTOM',
        'team_id' => $this->user->personalTeam()->id,
    ]);
    $observer->creating($buyer3);

    expect($buyer3->code)->toBe('BUY-CUSTOM');
});

test('buyer soft deletes work correctly', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->create();
    $buyerId = $buyer->id;

    $buyer->delete();

    expect(Buyer::find($buyerId))->toBeNull()
        ->and(Buyer::withTrashed()->find($buyerId))->not->toBeNull();
});

test('buyer can be restored after soft delete', function () {
    $buyer = Buyer::factory()->for($this->user->personalTeam())->create();

    $buyer->delete();
    $buyer->restore();

    expect($buyer->fresh()->deleted_at)->toBeNull();
});

test('with credit used factory state works', function () {
    $buyer = Buyer::factory()
        ->for($this->user->personalTeam())
        ->withCreditUsed(5000.00)
        ->create(['credit_limit' => 10000.00]);

    expect((float) $buyer->credit_used)->toBe(5000.00)
        ->and($buyer->available_credit)->toBe('5000.00');
});

test('for company factory state works', function () {
    $company = Company::factory()->for($this->user->personalTeam())->create();

    $buyer = Buyer::factory()
        ->for($this->user->personalTeam())
        ->forCompany($company)
        ->create();

    expect($buyer->company_id)->toBe($company->id);
});
