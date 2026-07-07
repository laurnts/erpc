<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Mail\Erp\QuoteToBuyerMail;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\EmailTemplate;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Team;
use App\Models\User;

function quoteToBuyerMailHierarchyFixture(): array
{
    $team = Team::factory()->create();
    $user = User::factory()->recycle($team)->create();
    test()->actingAs($user);

    $buyer = Company::factory()->buyer()->recycle($team)->create();
    $currency = Currency::factory()->create();
    $request = Request::factory()->recycle($team)->recycle($buyer)->create();
    $mainReqItemA = RequestItem::factory()->recycle($request)->create([
        'parent_id' => null,
        'item_type' => ItemType::SERVICE,
    ]);
    $childReqItemA1 = RequestItem::factory()->recycle($request)->create(['parent_id' => $mainReqItemA->getKey()]);
    $childReqItemA2 = RequestItem::factory()->recycle($request)->create(['parent_id' => $mainReqItemA->getKey()]);
    $mainReqItemB = RequestItem::factory()->recycle($request)->create([
        'parent_id' => null,
        'item_type' => ItemType::SERVICE,
    ]);

    $quote = BuyerQuote::factory()
        ->recycle($team)
        ->recycle($buyer)
        ->forRequest($request)
        ->withCurrency($currency)
        ->create();

    BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
        'request_item_id' => $mainReqItemA->getKey(),
        'description' => 'Main work A',
        'sort_order' => 1,
    ]);
    BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
        'request_item_id' => $childReqItemA1->getKey(),
        'description' => 'Child work A1',
        'sort_order' => 2,
    ]);
    BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
        'request_item_id' => $mainReqItemB->getKey(),
        'description' => 'Main work B',
        'sort_order' => 3,
    ]);
    BuyerQuoteItem::factory()->forBuyerQuote($quote)->create([
        'request_item_id' => $childReqItemA2->getKey(),
        'description' => 'Child work A2',
        'sort_order' => 4,
    ]);

    $quote->load(['items.requestItem', 'buyer', 'team', 'request', 'currency']);

    return compact('team', 'quote');
}

function expectHierarchicalItemOrder(string $html): void
{
    $mainAPos = strpos($html, 'Main work A');
    $childA1Pos = strpos($html, 'Child work A1');
    $childA2Pos = strpos($html, 'Child work A2');
    $mainBPos = strpos($html, 'Main work B');

    expect($mainAPos)->not->toBeFalse()
        ->and($childA1Pos)->not->toBeFalse()
        ->and($childA2Pos)->not->toBeFalse()
        ->and($mainBPos)->not->toBeFalse()
        ->and($mainAPos)->toBeLessThan($childA1Pos)
        ->and($childA1Pos)->toBeLessThan($childA2Pos)
        ->and($childA2Pos)->toBeLessThan($mainBPos);
}

it('orders buyer quote email items hierarchically in the default blade template', function (): void {
    ['team' => $team, 'quote' => $quote] = quoteToBuyerMailHierarchyFixture();

    $html = view('emails.quote-to-buyer', [
        'quote' => $quote,
        'content' => '',
        'team' => $team,
    ])->render();

    expectHierarchicalItemOrder($html);
});

it('orders buyer quote email items hierarchically when using a full HTML template', function (): void {
    ['team' => $team, 'quote' => $quote] = quoteToBuyerMailHierarchyFixture();

    $template = EmailTemplate::query()->create([
        'team_id' => $team->getKey(),
        'type' => EmailTemplate::TYPE_BUYER_QUOTE,
        'name' => 'Test Full HTML Buyer Quote',
        'content' => <<<'HTML'
<!DOCTYPE html>
<html><body>
<p>Dear {{buyer_name}},</p>
@if($quote->items && $quote->items->count() > 0)
<table><tbody>
@foreach($quote->items as $index => $item)
<tr><td>{{ $item->description }}</td></tr>
@endforeach
</tbody></table>
@endif
</body></html>
HTML,
        'is_default' => false,
    ]);

    $settings = $team->getErpSettings();
    $settings->email_template_buyer_quote_id = $template->getKey();
    $team->update(['erp_settings' => $settings]);

    $html = (new QuoteToBuyerMail($quote))->render();

    expectHierarchicalItemOrder($html);
});
