<?php

declare(strict_types=1);

use App\Models\PortalInvitation;

it('is expired when expires_at is in the past', function (): void {
    $invitation = new PortalInvitation(['expires_at' => now()->subDay()]);

    expect($invitation->isExpired())->toBeTrue();
});

it('is not expired when expires_at is in the future', function (): void {
    $invitation = new PortalInvitation(['expires_at' => now()->addDay()]);

    expect($invitation->isExpired())->toBeFalse();
});

it('is not expired when expires_at is null', function (): void {
    $invitation = new PortalInvitation(['expires_at' => null]);

    expect($invitation->isExpired())->toBeFalse();
});
