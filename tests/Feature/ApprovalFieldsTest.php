<?php

declare(strict_types=1);

use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->user->teams()->attach($this->team);
    actingAs($this->user);

    // Approval fields reference team members (User), not People records.
    $this->preparedBy = User::factory()->create();
    $this->preparedBy->teams()->attach($this->team);

    $this->deptHead = User::factory()->create();
    $this->deptHead->teams()->attach($this->team);

    $this->deputyDirector = User::factory()->create();
    $this->deputyDirector->teams()->attach($this->team);

    $this->approvedBy = User::factory()->create();
    $this->approvedBy->teams()->attach($this->team);
});

test('QuotationEvaluation can have approval relationships', function (): void {
    $request = Request::factory()->create([
        'team_id' => $this->team->id,
        'creator_id' => $this->user->id,
    ]);

    $qe = QuotationEvaluation::factory()->create([
        'team_id' => $this->team->id,
        'request_id' => $request->id,
        'prepared_by_id' => $this->preparedBy->id,
        'dept_head_sales_id' => $this->deptHead->id,
        'deputy_director_id' => $this->deputyDirector->id,
        'approved_by_id' => $this->approvedBy->id,
        'creator_id' => $this->user->id,
    ]);

    expect($qe->preparedBy)->toBeInstanceOf(User::class)
        ->and($qe->preparedBy->id)->toBe($this->preparedBy->id)
        ->and($qe->deptHeadSales)->toBeInstanceOf(User::class)
        ->and($qe->deptHeadSales->id)->toBe($this->deptHead->id)
        ->and($qe->deputyDirector)->toBeInstanceOf(User::class)
        ->and($qe->deputyDirector->id)->toBe($this->deputyDirector->id)
        ->and($qe->approvedBy)->toBeInstanceOf(User::class)
        ->and($qe->approvedBy->id)->toBe($this->approvedBy->id);
});

test('ProfitAndLoss can have approval relationships', function (): void {
    $request = Request::factory()->create([
        'team_id' => $this->team->id,
        'creator_id' => $this->user->id,
    ]);

    $pnl = ProfitAndLoss::factory()->create([
        'team_id' => $this->team->id,
        'request_id' => $request->id,
        'prepared_by_id' => $this->preparedBy->id,
        'dept_head_sales_id' => $this->deptHead->id,
        'deputy_director_id' => $this->deputyDirector->id,
        'approved_by_id' => $this->approvedBy->id,
        'creator_id' => $this->user->id,
    ]);

    expect($pnl->preparedBy)->toBeInstanceOf(User::class)
        ->and($pnl->preparedBy->id)->toBe($this->preparedBy->id)
        ->and($pnl->deptHeadSales)->toBeInstanceOf(User::class)
        ->and($pnl->deptHeadSales->id)->toBe($this->deptHead->id)
        ->and($pnl->deputyDirector)->toBeInstanceOf(User::class)
        ->and($pnl->deputyDirector->id)->toBe($this->deputyDirector->id)
        ->and($pnl->approvedBy)->toBeInstanceOf(User::class)
        ->and($pnl->approvedBy->id)->toBe($this->approvedBy->id);
});

test('QuotationEvaluation approval relationships are nullable', function (): void {
    $request = Request::factory()->create([
        'team_id' => $this->team->id,
        'creator_id' => $this->user->id,
    ]);

    $qe = QuotationEvaluation::factory()->create([
        'team_id' => $this->team->id,
        'request_id' => $request->id,
        'prepared_by_id' => null,
        'dept_head_sales_id' => null,
        'deputy_director_id' => null,
        'approved_by_id' => null,
        'creator_id' => $this->user->id,
    ]);

    expect($qe->preparedBy)->toBeNull()
        ->and($qe->deptHeadSales)->toBeNull()
        ->and($qe->deputyDirector)->toBeNull()
        ->and($qe->approvedBy)->toBeNull();
});
