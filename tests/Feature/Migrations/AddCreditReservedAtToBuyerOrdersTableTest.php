<?php

declare(strict_types=1);

use App\Models\BuyerOrder;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Every writer (App\Models\BuyerOrder::confirm/cancel/reconcile) stamps
 * related_type with the FQCN, never the bare 'buyer_order' alias — that alias
 * exists only in hand-seeded dev data. The backfill must match the FQCN form
 * or it silently seeds nothing on a real database.
 */
it('backfills credit_reserved_at from an FQCN-stamped history row', function (): void {
    $team = Team::factory()->create();
    $currency = Currency::factory()->create();
    $buyer = Company::factory()->buyer()->recycle($team)->create();
    $request = Request::factory()->recycle($team)->recycle($buyer)->create();

    $order = BuyerOrder::factory()
        ->recycle($team)
        ->recycle($currency)
        ->for($buyer, 'buyer')
        ->for($request)
        ->create();

    $migration = require database_path('migrations/2026_07_29_120000_add_credit_reserved_at_to_buyer_orders_table.php');
    $migration->down();

    DB::table('buyer_credit_usage_histories')->insert([
        'team_id' => $team->id,
        'buyer_id' => $buyer->id,
        'transaction_type' => 'debit',
        'amount' => 5000,
        'available_credit_before' => 100000,
        'available_credit_after' => 95000,
        'credit_used_before' => 0,
        'credit_used_after' => 5000,
        'related_type' => BuyerOrder::class,
        'related_id' => $order->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    expect(DB::table('buyer_orders')->where('id', $order->id)->value('credit_reserved_at'))->not->toBeNull();
});
