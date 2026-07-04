<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PNLStatus;
use App\Enums\QEStatus;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerOrdersRelationManager;
use App\Mail\Erp\BuyerOrderToBuyerMail;
use App\Models\BuyerCreditUsageHistory;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    $this->buyer = Company::factory()->buyer()->for($this->team)->create([
        'email' => 'buyer@example.com',
    ]);
    $this->request = Request::factory()->for($this->team)->recycle($this->buyer)->create();

    QuotationEvaluation::factory()
        ->recycle($this->user)
        ->forRequest($this->request)
        ->create(['status' => QEStatus::APPROVED]);

    ProfitAndLoss::factory()
        ->recycle($this->user)
        ->forRequest($this->request)
        ->create(['status' => PNLStatus::APPROVED]);

    $this->buyerQuote = BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->user)
        ->accepted()
        ->create([
            'request_id' => $this->request,
            'buyer_id' => $this->buyer,
        ]);
});

/**
 * Create a buyer order on the acting team's request.
 *
 * @param  array<string, mixed>  $attributes
 */
function buyerOrderActionRecord(Tests\TestCase $test, array $attributes = []): BuyerOrder
{
    return BuyerOrder::factory()
        ->recycle($test->team)
        ->recycle($test->user)
        ->forRequest($test->request)
        ->forBuyer($test->buyer)
        ->create($attributes);
}

/**
 * Mount the buyer orders relation manager for the acting team's request.
 */
function buyerOrdersRelationManager(Tests\TestCase $test): Testable
{
    return livewire(BuyerOrdersRelationManager::class, [
        'ownerRecord' => $test->request,
        'pageClass' => ViewRequest::class,
    ]);
}

describe('send action', function (): void {
    it('sends a draft order to the buyer and marks it as sent', function (): void {
        Mail::fake();

        $order = buyerOrderActionRecord($this, ['status' => OrderStatus::DRAFT]);

        buyerOrdersRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('send')->table($order))
            ->callAction(TestAction::make('send')->table($order))
            ->assertNotified('Order sent');

        expect($order->refresh()->status)->toBe(OrderStatus::SENT);

        Mail::assertSent(
            BuyerOrderToBuyerMail::class,
            fn (BuyerOrderToBuyerMail $mail): bool => $mail->order->is($order)
                && $mail->hasTo('buyer@example.com')
        );
    });

    it('marks the order as sent without email when the buyer has no address', function (): void {
        Mail::fake();

        $this->buyer->update(['email' => null]);
        $order = buyerOrderActionRecord($this, ['status' => OrderStatus::DRAFT]);

        buyerOrdersRelationManager($this)
            ->assertOk()
            ->callAction(TestAction::make('send')->table($order))
            ->assertNotified('Order marked as sent');

        expect($order->refresh()->status)->toBe(OrderStatus::SENT);

        Mail::assertNothingSent();
    });

    it('hides send for an approved order (pins the credit double-reduction backdoor closure)', function (): void {
        Mail::fake();

        $order = buyerOrderActionRecord($this, ['status' => OrderStatus::APPROVED]);

        buyerOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('send')->table($order));

        expect($order->refresh()->status)->toBe(OrderStatus::APPROVED);

        Mail::assertNothingSent();
    });

    it('hides send for a sent order and shows resend instead', function (): void {
        $order = buyerOrderActionRecord($this, ['status' => OrderStatus::SENT]);

        buyerOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('send')->table($order))
            ->assertActionVisible(TestAction::make('resend')->table($order));
    });
});

describe('resend action', function (): void {
    it('resends the order email without changing the status', function (): void {
        Mail::fake();

        $order = buyerOrderActionRecord($this, ['status' => OrderStatus::SENT]);

        buyerOrdersRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('resend')->table($order))
            ->callAction(TestAction::make('resend')->table($order))
            ->assertNotified('Email resent');

        expect($order->refresh()->status)->toBe(OrderStatus::SENT);

        Mail::assertSent(
            BuyerOrderToBuyerMail::class,
            fn (BuyerOrderToBuyerMail $mail): bool => $mail->order->is($order)
                && $mail->hasTo('buyer@example.com')
        );
    });

    it('warns without sending when the buyer has no email address', function (): void {
        Mail::fake();

        $this->buyer->update(['email' => null]);
        $order = buyerOrderActionRecord($this, ['status' => OrderStatus::SENT]);

        buyerOrdersRelationManager($this)
            ->assertOk()
            ->callAction(TestAction::make('resend')->table($order))
            ->assertNotified('Cannot resend email');

        expect($order->refresh()->status)->toBe(OrderStatus::SENT);

        Mail::assertNothingSent();
    });

    it('hides resend for a draft order', function (): void {
        $order = buyerOrderActionRecord($this, ['status' => OrderStatus::DRAFT]);

        buyerOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('resend')->table($order));
    });
});

describe('cancel action', function (): void {
    it('cancels a draft order without touching buyer credit', function (): void {
        $this->buyer->update([
            'available_credit' => '600.00',
            'credit_used' => '400.00',
        ]);

        $order = buyerOrderActionRecord($this, [
            'status' => OrderStatus::DRAFT,
            'total' => '400.00',
        ]);

        buyerOrdersRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('cancel')->table($order))
            ->callAction(TestAction::make('cancel')->table($order))
            ->assertNotified('Order cancelled');

        $this->buyer->refresh();

        expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED)
            ->and((float) $this->buyer->available_credit)->toBe(600.00)
            ->and((float) $this->buyer->credit_used)->toBe(400.00);
    });

    it('cancels a confirmed order and restores buyer credit', function (): void {
        $this->buyer->update([
            'credit_status' => true,
            'available_credit' => '600.00',
            'credit_used' => '400.00',
        ]);

        $order = buyerOrderActionRecord($this, [
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => now(),
            'total' => '400.00',
        ]);

        buyerOrdersRelationManager($this)
            ->assertOk()
            ->callAction(TestAction::make('cancel')->table($order))
            ->assertNotified('Order cancelled');

        $this->buyer->refresh();

        expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED)
            ->and((float) $this->buyer->available_credit)->toBe(1000.00)
            ->and((float) $this->buyer->credit_used)->toBe(0.00)
            ->and(
                BuyerCreditUsageHistory::query()
                    ->where('related_type', BuyerOrder::class)
                    ->where('related_id', $order->getKey())
                    ->where('transaction_type', 'credit')
                    ->exists()
            )->toBeTrue();
    });

    it('hides cancel for a completed order', function (): void {
        $order = buyerOrderActionRecord($this, [
            'status' => OrderStatus::COMPLETED,
            'confirmed_at' => now(),
        ]);

        buyerOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('cancel')->table($order));

        expect($order->refresh()->status)->toBe(OrderStatus::COMPLETED);
    });

    it('hides cancel for an already cancelled order', function (): void {
        $order = buyerOrderActionRecord($this, ['status' => OrderStatus::CANCELLED]);

        buyerOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('cancel')->table($order));
    });
});

describe('permission gating', function (): void {
    /**
     * KNOWN GAP (intentionally failing): the send/resend/cancel actions in
     * BuyerOrdersRelationManager gate visibility on order status only. They never
     * consult BuyerOrderPolicy (whose cancel() requires the 'update buyer orders'
     * permission), so a view-only team member ('viewer' Spatie role, non-admin
     * Jetstream role) can see and invoke them. This test asserts the correct
     * behavior and should pass once the actions are wired to the policy
     * (e.g. ->authorize() or a policy check inside visible()).
     */
    it('hides send and cancel from a view-only team member', function (): void {
        $order = buyerOrderActionRecord($this, ['status' => OrderStatus::DRAFT]);

        $member = User::factory()->create();
        $this->team->users()->attach($member, ['role' => 'editor']);

        $this->actingAs($member);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->team);

        buyerOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('send')->table($order))
            ->assertActionHidden(TestAction::make('cancel')->table($order));

        expect($order->refresh()->status)->toBe(OrderStatus::DRAFT);
    });
});
