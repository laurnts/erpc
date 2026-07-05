<?php

declare(strict_types=1);

use App\Enums\CentralPurchasingRole;
use App\Enums\OrderStatus;
use App\Enums\PNLStatus;
use App\Enums\QEStatus;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierOrdersRelationManager;
use App\Mail\Erp\PurchaseOrderToSupplierMail;
use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierOrder;
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

    $this->buyer = Company::factory()->buyer()->for($this->team)->create();
    $this->supplier = Company::factory()->supplier()->for($this->team)->create([
        'email' => 'supplier@example.com',
    ]);
    $this->currency = Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
    $this->request = Request::factory()->for($this->team)->recycle($this->buyer)->create();

    QuotationEvaluation::factory()
        ->recycle($this->user)
        ->forRequest($this->request)
        ->create(['status' => QEStatus::APPROVED]);

    ProfitAndLoss::factory()
        ->recycle($this->user)
        ->forRequest($this->request)
        ->create(['status' => PNLStatus::APPROVED]);

    BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->user)
        ->accepted()
        ->create([
            'request_id' => $this->request,
            'buyer_id' => $this->buyer,
        ]);
});

/**
 * Create a supplier order on the acting team's request in the given status.
 *
 * @param  array<string, mixed>  $attributes
 */
function supplierOrderInStatus(Tests\TestCase $test, OrderStatus $status, array $attributes = []): SupplierOrder
{
    return SupplierOrder::factory()
        ->for($test->team)
        ->recycle($test->request)
        ->recycle($test->supplier)
        ->recycle($test->currency)
        ->withStatus($status)
        ->create($attributes);
}

/**
 * Mount the SupplierOrders relation manager on the request view page.
 */
function mountSupplierOrdersRelationManager(Tests\TestCase $test): Testable
{
    return livewire(SupplierOrdersRelationManager::class, [
        'ownerRecord' => $test->request,
        'pageClass' => ViewRequest::class,
    ]);
}

describe('send action', function (): void {
    it('marks an approved order as sent and emails the supplier', function (): void {
        Mail::fake();
        $order = supplierOrderInStatus($this, OrderStatus::APPROVED);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('send')->table($order))
            ->callAction(TestAction::make('send')->table($order))
            ->assertNotified('Order sent');

        $order->refresh();

        expect($order->status)->toBe(OrderStatus::SENT)
            ->and($order->ordered_at)->not->toBeNull();

        Mail::assertSent(
            PurchaseOrderToSupplierMail::class,
            fn (PurchaseOrderToSupplierMail $mail): bool => $mail->hasTo('supplier@example.com'),
        );
    });

    it('marks the order as sent with a warning when the supplier has no email', function (): void {
        Mail::fake();
        $this->supplier->update(['email' => null]);
        $order = supplierOrderInStatus($this, OrderStatus::APPROVED);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->callAction(TestAction::make('send')->table($order))
            ->assertNotified('Order marked as sent');

        expect($order->refresh()->status)->toBe(OrderStatus::SENT);

        Mail::assertNothingSent();
    });

    it('hides the send action when the order status does not allow sending', function (OrderStatus $status): void {
        $order = supplierOrderInStatus($this, $status);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('send')->table($order));
    })->with([
        'draft' => OrderStatus::DRAFT,
        'confirmed' => OrderStatus::CONFIRMED,
        'sent' => OrderStatus::SENT,
        'processing' => OrderStatus::PROCESSING,
        'cancelled' => OrderStatus::CANCELLED,
    ]);
});

describe('bulk send action', function (): void {
    it('is visible when at least one approved order exists', function (): void {
        supplierOrderInStatus($this, OrderStatus::APPROVED);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('sendAllToSuppliers')->table());
    });

    it('is hidden when no approved orders exist', function (): void {
        supplierOrderInStatus($this, OrderStatus::CONFIRMED);
        supplierOrderInStatus($this, OrderStatus::SENT, ['ordered_at' => now()]);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('sendAllToSuppliers')->table());
    });

    it('sends every approved order and leaves other statuses untouched', function (): void {
        Mail::fake();
        $secondSupplier = Company::factory()->supplier()->for($this->team)->create([
            'email' => 'second@example.com',
        ]);

        $approvedA = supplierOrderInStatus($this, OrderStatus::APPROVED);
        $approvedB = SupplierOrder::factory()
            ->for($this->team)
            ->recycle($this->request)
            ->recycle($this->currency)
            ->withStatus(OrderStatus::APPROVED)
            ->create(['supplier_id' => $secondSupplier->getKey()]);
        $draft = supplierOrderInStatus($this, OrderStatus::DRAFT);

        mountSupplierOrdersRelationManager($this)
            ->callAction(TestAction::make('sendAllToSuppliers')->table())
            ->assertNotified();

        expect($approvedA->refresh()->status)->toBe(OrderStatus::SENT)
            ->and($approvedB->refresh()->status)->toBe(OrderStatus::SENT)
            ->and($draft->refresh()->status)->toBe(OrderStatus::DRAFT);

        Mail::assertSent(PurchaseOrderToSupplierMail::class, 2);
    });
});

describe('resend action', function (): void {
    it('resends the purchase order email without changing the order status', function (): void {
        Mail::fake();
        $order = supplierOrderInStatus($this, OrderStatus::SENT, ['ordered_at' => now()->subDay()]);
        $orderedAt = $order->ordered_at;

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('resend')->table($order))
            ->callAction(TestAction::make('resend')->table($order))
            ->assertNotified('Email resent');

        $order->refresh();

        expect($order->status)->toBe(OrderStatus::SENT)
            ->and($order->ordered_at?->equalTo($orderedAt))->toBeTrue();

        Mail::assertSent(
            PurchaseOrderToSupplierMail::class,
            fn (PurchaseOrderToSupplierMail $mail): bool => $mail->hasTo('supplier@example.com'),
        );
    });

    it('warns and sends nothing when the supplier has no email', function (): void {
        Mail::fake();
        $this->supplier->update(['email' => null]);
        $order = supplierOrderInStatus($this, OrderStatus::SENT);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->callAction(TestAction::make('resend')->table($order))
            ->assertNotified('Cannot resend email');

        expect($order->refresh()->status)->toBe(OrderStatus::SENT);

        Mail::assertNothingSent();
    });

    it('hides the resend action for orders that have not been sent', function (OrderStatus $status): void {
        $order = supplierOrderInStatus($this, $status);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('resend')->table($order));
    })->with([
        'draft' => OrderStatus::DRAFT,
        'approved' => OrderStatus::APPROVED,
    ]);
});

describe('cancel action', function (): void {
    it('cancels a confirmed order', function (): void {
        $order = supplierOrderInStatus($this, OrderStatus::CONFIRMED, ['confirmed_at' => now()]);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('cancel')->table($order))
            ->callAction(TestAction::make('cancel')->table($order))
            ->assertNotified('Order cancelled');

        expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED);
    });

    it('hides the cancel action once the order has progressed past confirmed or is terminal', function (OrderStatus $status): void {
        $order = supplierOrderInStatus($this, $status);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('cancel')->table($order));
    })->with([
        'processing' => OrderStatus::PROCESSING,
        'completed' => OrderStatus::COMPLETED,
        'cancelled' => OrderStatus::CANCELLED,
    ]);
});

/**
 * Attach a user to the team with a Central Purchasing approval role.
 */
function grantPurchaseApprovalRole(
    Tests\TestCase $test,
    User $user,
    CentralPurchasingRole $role = CentralPurchasingRole::DIRECTOR,
): void {
    $test->team->users()->attach($user, [
        'role' => 'central_purchasing',
        'central_purchasing_role' => $role->value,
    ]);

    $user->unsetRelation('teams');
}

describe('approve action', function (): void {
    it('lets an eligible approver record the first approval from the supplier orders table', function (): void {
        grantPurchaseApprovalRole($this, $this->user);
        $order = supplierOrderInStatus($this, OrderStatus::CONFIRMED);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertActionVisible(TestAction::make('approve')->table($order))
            ->callAction(TestAction::make('approve')->table($order))
            ->assertNotified('Approval recorded');

        $order->refresh();

        expect($order->approver_1_id)->toBe($this->user->getKey())
            ->and($order->status)->toBe(OrderStatus::CONFIRMED)
            ->and($order->approved_at)->toBeNull();
    });

    it('marks the order approved on the second approval', function (): void {
        $firstApprover = User::factory()->create();
        grantPurchaseApprovalRole($this, $firstApprover, CentralPurchasingRole::DEPUTY_DIRECTOR);
        grantPurchaseApprovalRole($this, $this->user);

        $order = supplierOrderInStatus($this, OrderStatus::CONFIRMED, [
            'approver_1_id' => $firstApprover->getKey(),
        ]);

        mountSupplierOrdersRelationManager($this)
            ->callAction(TestAction::make('approve')->table($order))
            ->assertNotified('Order approved');

        $order->refresh();

        expect($order->approver_2_id)->toBe($this->user->getKey())
            ->and($order->status)->toBe(OrderStatus::APPROVED)
            ->and($order->approved_at)->not->toBeNull();
    });

    it('hides approve from a team member without an approval role', function (): void {
        $order = supplierOrderInStatus($this, OrderStatus::CONFIRMED);

        $member = User::factory()->create();
        $this->team->users()->attach($member, ['role' => 'editor']);

        $this->actingAs($member);
        Filament::setTenant($this->team);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('approve')->table($order));
    });

    it('hides approve once the user has already approved', function (): void {
        grantPurchaseApprovalRole($this, $this->user);
        $order = supplierOrderInStatus($this, OrderStatus::CONFIRMED, [
            'approver_1_id' => $this->user->getKey(),
        ]);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('approve')->table($order));
    });

    it('hides approve on a draft order', function (): void {
        grantPurchaseApprovalRole($this, $this->user);
        $order = supplierOrderInStatus($this, OrderStatus::DRAFT);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('approve')->table($order));
    });
});

describe('approvals column', function (): void {
    it('shows approval progress for a confirmed order', function (): void {
        $firstApprover = User::factory()->create();
        grantPurchaseApprovalRole($this, $firstApprover, CentralPurchasingRole::DEPUTY_DIRECTOR);

        supplierOrderInStatus($this, OrderStatus::CONFIRMED, [
            'approver_1_id' => $firstApprover->getKey(),
        ]);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertSee('1/2');
    });
});

describe('status display', function (): void {
    it('shows a confirmed order as Awaiting Approval in the supplier orders table', function (): void {
        supplierOrderInStatus($this, OrderStatus::CONFIRMED);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertSee('Awaiting Approval');
    });
});

describe('permission gating', function (): void {
    /**
     * KNOWN BUG (pins correct behavior, currently failing): the send, resend, and cancel
     * record actions on SupplierOrdersRelationManager gate visibility on order status only
     * and never check SupplierOrderPolicy or the 'update supplier orders' permission.
     * A team member holding only the default 'viewer' Spatie role (read-only permissions)
     * can see and execute these state-changing actions, including emailing the supplier.
     */
    it('hides send and cancel from a team member without supplier order write permissions', function (): void {
        $sendableOrder = supplierOrderInStatus($this, OrderStatus::APPROVED);

        $member = User::factory()->create();
        $this->team->users()->attach($member, ['role' => 'editor']);

        $this->actingAs($member);
        Filament::setTenant($this->team);

        mountSupplierOrdersRelationManager($this)
            ->assertOk()
            ->assertActionHidden(TestAction::make('send')->table($sendableOrder))
            ->assertActionHidden(TestAction::make('cancel')->table($sendableOrder));
    });
});

describe('tab label', function (): void {
    it('labels the request page tab Supplier Orders', function (): void {
        $tab = SupplierOrdersRelationManager::getTabComponent($this->request, ViewRequest::class);

        expect($tab->getLabel())->toBe('Supplier Orders');
    });
});
