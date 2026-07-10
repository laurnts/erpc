<?php

declare(strict_types=1);

use App\Enums\NoteVisibility;

it('exposes timeline badge labels per visibility', function (): void {
    expect(NoteVisibility::Buyer->getTimelineBadgeLabel())->toBe('Notes: To Buyer')
        ->and(NoteVisibility::Supplier->getTimelineBadgeLabel())->toBe('Notes: To Supplier')
        ->and(NoteVisibility::Internal->getTimelineBadgeLabel())->toBe('Notes: Internal');
});
