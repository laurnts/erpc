<?php

declare(strict_types=1);

use App\Models\People;
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

    $this->preparedBy = People::factory()->create([
        'team_id' => $this->team->id,
        'is_key_account' => true,
        'creator_id' => $this->user->id,
    ]);

    $this->deptHead = People::factory()->create([
        'team_id' => $this->team->id,
        'is_key_account' => true,
        'creator_id' => $this->user->id,
    ]);

    $this->deputyDirector = People::factory()->create([
        'team_id' => $this->team->id,
        'is_key_account' => true,
        'creator_id' => $this->user->id,
    ]);

    $this->approvedBy = People::factory()->create([
        'team_id' => $this->team->id,
        'is_key_account' => true,
        'creator_id' => $this->user->id,
    ]);
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

    expect($qe->preparedBy)->toBeInstanceOf(People::class)
        ->and($qe->preparedBy->id)->toBe($this->preparedBy->id)
        ->and($qe->deptHeadSales)->toBeInstanceOf(People::class)
        ->and($qe->deptHeadSales->id)->toBe($this->deptHead->id)
        ->and($qe->deputyDirector)->toBeInstanceOf(People::class)
        ->and($qe->deputyDirector->id)->toBe($this->deputyDirector->id)
        ->and($qe->approvedBy)->toBeInstanceOf(People::class)
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

    expect($pnl->preparedBy)->toBeInstanceOf(People::class)
        ->and($pnl->preparedBy->id)->toBe($this->preparedBy->id)
        ->and($pnl->deptHeadSales)->toBeInstanceOf(People::class)
        ->and($pnl->deptHeadSales->id)->toBe($this->deptHead->id)
        ->and($pnl->deputyDirector)->toBeInstanceOf(People::class)
        ->and($pnl->deputyDirector->id)->toBe($this->deputyDirector->id)
        ->and($pnl->approvedBy)->toBeInstanceOf(People::class)
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
