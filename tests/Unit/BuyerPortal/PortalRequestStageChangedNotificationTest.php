<?php

declare(strict_types=1);

use App\Enums\RequestStage;
use App\Models\Company;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use App\Notifications\PortalRequestStageChangedNotification;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->for($this->team)->create();
});

it('describes internal sourcing stages with buyer-safe wording in the stage-changed email', function (): void {
    $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
        'stage' => RequestStage::PREPARING_SUPPLIER_ORDER,
    ]);

    $mail = (new PortalRequestStageChangedNotification($request))
        ->toMail(User::factory()->make());

    $lines = collect($mail->introLines)->implode(' ');

    expect($lines)->toContain('Being Processed')
        ->not->toContain('Supplier Orders');
});

it('uses buyer-safe wording in the database notification body', function (): void {
    $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
        'stage' => RequestStage::AWAITING_SUPPLIER_RESPONSE,
    ]);

    $payload = (new PortalRequestStageChangedNotification($request))
        ->toDatabase(User::factory()->make());

    expect($payload['body'])->toContain('Sourcing Quotes')
        ->not->toContain('Supplier Quotes');
});
