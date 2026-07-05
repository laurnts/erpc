<?php

declare(strict_types=1);

use App\Enums\OrderStatus;

describe('getSupplierOrderLabel', function (): void {
    it('labels a confirmed purchase order as awaiting approval', function (): void {
        expect(OrderStatus::CONFIRMED->getSupplierOrderLabel())->toBe('Awaiting Approval');
    });

    it('matches the default label for every other status', function (OrderStatus $status): void {
        expect($status->getSupplierOrderLabel())->toBe($status->getLabel());
    })->with(array_filter(OrderStatus::cases(), fn (OrderStatus $status): bool => $status !== OrderStatus::CONFIRMED));
});
