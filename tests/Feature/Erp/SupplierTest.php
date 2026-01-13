<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
});

// Model Tests
test('supplier can be created via factory', function () {
    $supplier = Supplier::factory()->for($this->user->personalTeam())->create([
        'name' => 'Test Supplier',
        'contact_person' => 'John Doe',
        'email' => 'supplier@example.com',
        'phone' => '+1234567890',
        'creator_id' => $this->user->id,
    ]);

    expect($supplier)->toBeInstanceOf(Supplier::class)
        ->and($supplier->name)->toBe('Test Supplier')
        ->and($supplier->contact_person)->toBe('John Doe')
        ->and($supplier->email)->toBe('supplier@example.com')
        ->and($supplier->phone)->toBe('+1234567890')
        ->and($supplier->team_id)->toBe($this->user->personalTeam()->id)
        ->and($supplier->creator_id)->toBe($this->user->id);
});

test('supplier belongs to team', function () {
    $supplier = Supplier::factory()->for($this->user->personalTeam())->create();

    expect($supplier->team)->toBeInstanceOf(Team::class)
        ->and($supplier->team->id)->toBe($this->user->personalTeam()->id);
});

test('supplier belongs to creator', function () {
    $supplier = Supplier::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    expect($supplier->creator)->toBeInstanceOf(User::class)
        ->and($supplier->creator->id)->toBe($this->user->id);
});

test('supplier has default values', function () {
    $supplier = Supplier::factory()->for($this->user->personalTeam())->create([
        'lead_time_days' => 0,
        'payment_terms_days' => 30,
        'is_active' => true,
    ]);

    expect($supplier->lead_time_days)->toBe(0)
        ->and($supplier->payment_terms_days)->toBe(30)
        ->and($supplier->is_active)->toBeTrue();
});

test('supplier code is unique per team', function () {
    $supplier1 = Supplier::factory()->for($this->user->personalTeam())->create(['code' => 'SUP-0001']);

    expect(fn () => Supplier::factory()->for($this->user->personalTeam())->create(['code' => 'SUP-0001']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('same supplier code can exist in different teams', function () {
    $user2 = User::factory()->withPersonalTeam()->create();

    $supplier1 = Supplier::factory()->for($this->user->personalTeam())->create(['code' => 'SUP-0001']);
    $supplier2 = Supplier::factory()->for($user2->personalTeam())->create(['code' => 'SUP-0001']);

    expect($supplier1->id)->not->toBe($supplier2->id)
        ->and($supplier1->code)->toBe($supplier2->code)
        ->and($supplier1->team_id)->not->toBe($supplier2->team_id);
});

test('supplier can be deactivated', function () {
    $supplier = Supplier::factory()->for($this->user->personalTeam())->create(['is_active' => true]);

    $supplier->update(['is_active' => false]);

    expect($supplier->fresh()->is_active)->toBeFalse();
});

test('supplier factory creates valid supplier', function () {
    $supplier = Supplier::factory()->for($this->user->personalTeam())->create();

    expect($supplier->name)->not->toBeEmpty()
        ->and($supplier->code)->not->toBeEmpty()
        ->and($supplier->team_id)->not->toBeNull()
        ->and($supplier->team_id)->toBe($this->user->personalTeam()->id);
});

test('inactive factory state works', function () {
    $supplier = Supplier::factory()->for($this->user->personalTeam())->inactive()->create();

    expect($supplier->is_active)->toBeFalse();
});

test('supplier can be linked to company', function () {
    $company = Company::factory()->for($this->user->personalTeam())->create();
    $supplier = Supplier::factory()
        ->for($this->user->personalTeam())
        ->withCompany($company)
        ->create();

    expect($supplier->company)->toBeInstanceOf(Company::class)
        ->and($supplier->company->id)->toBe($company->id);
});

test('supplier can have default currency', function () {
    $currency = Currency::factory()->create(['code' => 'USD']);
    $supplier = Supplier::factory()
        ->for($this->user->personalTeam())
        ->withDefaultCurrency($currency)
        ->create();

    expect($supplier->defaultCurrency)->toBeInstanceOf(Currency::class)
        ->and($supplier->defaultCurrency->id)->toBe($currency->id);
});

test('supplier can have tags', function () {
    $supplier = Supplier::factory()->for($this->user->personalTeam())->create();
    $tag1 = Tag::factory()->for($this->user->personalTeam())->create(['name' => 'Preferred']);
    $tag2 = Tag::factory()->for($this->user->personalTeam())->create(['name' => 'Local']);

    $supplier->tags()->attach([$tag1->id, $tag2->id]);

    expect($supplier->tags)->toHaveCount(2)
        ->and($supplier->tags->pluck('name')->toArray())->toContain('Preferred', 'Local');
});

test('supplier can sync tags', function () {
    $supplier = Supplier::factory()->for($this->user->personalTeam())->create();
    $tag1 = Tag::factory()->for($this->user->personalTeam())->create();
    $tag2 = Tag::factory()->for($this->user->personalTeam())->create();
    $tag3 = Tag::factory()->for($this->user->personalTeam())->create();

    $supplier->syncTags([$tag1->id, $tag2->id]);
    expect($supplier->fresh()->tags)->toHaveCount(2);

    $supplier->syncTags([$tag2->id, $tag3->id]);
    expect($supplier->fresh()->tags)->toHaveCount(2)
        ->and($supplier->tags->pluck('id')->toArray())->toContain($tag2->id, $tag3->id)
        ->and($supplier->tags->pluck('id')->toArray())->not->toContain($tag1->id);
});

test('supplier hasTag method works', function () {
    $supplier = Supplier::factory()->for($this->user->personalTeam())->create();
    $tag = Tag::factory()->for($this->user->personalTeam())->create();
    $otherTag = Tag::factory()->for($this->user->personalTeam())->create();

    $supplier->attachTags([$tag->id]);

    expect($supplier->hasTag($tag))->toBeTrue()
        ->and($supplier->hasTag($tag->id))->toBeTrue()
        ->and($supplier->hasTag($otherTag))->toBeFalse();
});

test('supplier can be soft deleted', function () {
    $supplier = Supplier::factory()->for($this->user->personalTeam())->create();

    $supplier->delete();

    expect(Supplier::find($supplier->id))->toBeNull()
        ->and(Supplier::withTrashed()->find($supplier->id))->not->toBeNull()
        ->and(Supplier::withTrashed()->find($supplier->id)->deleted_at)->not->toBeNull();
});

test('supplier can be restored', function () {
    $supplier = Supplier::factory()->for($this->user->personalTeam())->create();

    $supplier->delete();
    Supplier::withTrashed()->find($supplier->id)->restore();

    expect(Supplier::find($supplier->id))->not->toBeNull()
        ->and(Supplier::find($supplier->id)->deleted_at)->toBeNull();
});

// Code Generation Tests
test('supplier code is auto-generated', function () {
    $supplier = Supplier::factory()->for($this->user->personalTeam())->create(['code' => null]);

    expect($supplier->code)->toMatch('/^SUP-\d{4}$/');
});

test('supplier code increments correctly', function () {
    Supplier::factory()->for($this->user->personalTeam())->create(['code' => 'SUP-0001']);
    Supplier::factory()->for($this->user->personalTeam())->create(['code' => 'SUP-0002']);
    $supplier3 = Supplier::factory()->for($this->user->personalTeam())->create(['code' => null]);

    expect($supplier3->code)->toBe('SUP-0003');
});

test('supplier code generation handles gaps', function () {
    Supplier::factory()->for($this->user->personalTeam())->create(['code' => 'SUP-0001']);
    Supplier::factory()->for($this->user->personalTeam())->create(['code' => 'SUP-0005']);
    $supplier3 = Supplier::factory()->for($this->user->personalTeam())->create(['code' => null]);

    expect($supplier3->code)->toBe('SUP-0006');
});

test('supplier code is team-scoped', function () {
    $user2 = User::factory()->withPersonalTeam()->create();

    Supplier::factory()->for($this->user->personalTeam())->create(['code' => 'SUP-0005']);
    $supplier2 = Supplier::factory()->for($user2->personalTeam())->create(['code' => null]);

    expect($supplier2->code)->toBe('SUP-0001');
});

// Factory State Tests
test('withLeadTime factory state works', function () {
    $supplier = Supplier::factory()
        ->for($this->user->personalTeam())
        ->withLeadTime(14)
        ->create();

    expect($supplier->lead_time_days)->toBe(14);
});

test('withPaymentTerms factory state works', function () {
    $supplier = Supplier::factory()
        ->for($this->user->personalTeam())
        ->withPaymentTerms(60)
        ->create();

    expect($supplier->payment_terms_days)->toBe(60);
});
