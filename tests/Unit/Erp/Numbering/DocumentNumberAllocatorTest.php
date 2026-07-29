<?php

declare(strict_types=1);

use App\Models\Team;
use App\Services\Erp\Numbering\DocumentNumberAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->allocator = app(DocumentNumberAllocator::class);
});

it('starts a fresh sequence at 1', function (): void {
    expect($this->allocator->next($this->team->getKey(), 'buyer_quote', '2026'))->toBe(1);
});

it('advances monotonically', function (): void {
    $teamId = $this->team->getKey();

    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(1);
    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(2);
    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(3);
});

it('keeps sequences independent per key', function (): void {
    $teamId = $this->team->getKey();

    $this->allocator->next($teamId, 'buyer_quote', '2026');
    $this->allocator->next($teamId, 'buyer_quote', '2026');

    expect($this->allocator->next($teamId, 'buyer_order', '2026'))->toBe(1);
});

it('keeps sequences independent per period', function (): void {
    $teamId = $this->team->getKey();

    $this->allocator->next($teamId, 'buyer_quote', '2026');

    expect($this->allocator->next($teamId, 'buyer_quote', '2027'))->toBe(1);
});

it('keeps sequences independent per team', function (): void {
    $other = Team::factory()->create();

    $this->allocator->next($this->team->getKey(), 'buyer_quote', '2026');

    expect($this->allocator->next($other->getKey(), 'buyer_quote', '2026'))->toBe(1);
});

it('crosses 9999 without regressing', function (): void {
    $teamId = $this->team->getKey();

    $this->allocator->seed($teamId, 'buyer_quote', '2026', 9999);

    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(9999);
    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(10000);
    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(10001);
});

it('peeks without advancing', function (): void {
    $teamId = $this->team->getKey();

    expect($this->allocator->peek($teamId, 'buyer_quote', '2026'))->toBe(1);
    expect($this->allocator->peek($teamId, 'buyer_quote', '2026'))->toBe(1);
    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(1);
});

it('seeds an existing sequence to a new value', function (): void {
    $teamId = $this->team->getKey();

    $this->allocator->next($teamId, 'buyer_quote', '2026');
    $this->allocator->seed($teamId, 'buyer_quote', '2026', 500);

    expect($this->allocator->next($teamId, 'buyer_quote', '2026'))->toBe(500);
});
