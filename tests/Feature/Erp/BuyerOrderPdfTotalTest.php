<?php

declare(strict_types=1);

use App\Models\BuyerOrder;
use App\Models\BuyerOrderItem;
use App\Models\Company;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use App\Services\Erp\PdfGenerationService;

it('sources buyer-order PDF totals from the stored order, not a re-sum of lines', function (): void {
    $team = Team::factory()->create();
    $user = User::factory()->recycle($team)->create();
    $this->actingAs($user);

    $buyer = Company::factory()->buyer()->recycle($team)->create();
    $request = Request::factory()->recycle($team)->recycle($buyer)->create();

    $order = BuyerOrder::factory()
        ->recycle($team)
        ->forBuyer($buyer)
        ->forRequest($request)
        ->create();

    // Item line totals deliberately sum to 1,600 — a re-sum would inflate the total.
    BuyerOrderItem::factory()->forBuyerOrder($order)->create(['line_total' => '1100']);
    BuyerOrderItem::factory()->forBuyerOrder($order)->create(['line_total' => '500']);

    // The stored document total is authoritative (1,100), distinct from the line sum.
    $order->forceFill(['subtotal' => '1000', 'tax_total' => '100', 'total' => '1100'])->saveQuietly();

    $data = app(PdfGenerationService::class)->buildBuyerOrderPdfData($order->fresh());

    expect($data['processedTotal'])->toBe(1100.0)      // stored, not the 1,600 line sum
        ->and($data['processedSubtotal'])->toBe(1000.0)
        ->and($data['processedTaxTotal'])->toBe(100.0);
});
