<?php

declare(strict_types=1);

use App\Enums\CentralPurchasingRole;
use App\Enums\OrderStatus;
use App\Filament\Resources\SupplierOrderApprovals\Pages\ListSupplierOrderApprovals;
use App\Filament\Resources\SupplierOrderApprovals\Pages\ViewSupplierOrderApproval;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    $this->supplier = Company::factory()->supplier()->for($this->team)->create();
    $this->currency = Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
    $this->request = Request::factory()->for($this->team)->create();
});

/**
 * Create a confirmed supplier order awaiting approval on the acting user's team.
 *
 * @param  array<string, mixed>  $attributes
 */
function confirmedApprovalOrder(Tests\TestCase $test, array $attributes = []): SupplierOrder
{
    return SupplierOrder::factory()
        ->for($test->team)
        ->recycle($test->request)
        ->recycle($test->supplier)
        ->recycle($test->currency)
        ->confirmed()
        ->create($attributes);
}

/**
 * Attach a user to the team with a Central Purchasing approval role.
 */
function grantApprovalRole(
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

describe('approve action on the view page', function (): void {
    it('records the first approval and keeps the order confirmed', function (): void {
        grantApprovalRole($this, $this->user);
        $order = confirmedApprovalOrder($this);

        livewire(ViewSupplierOrderApproval::class, ['record' => $order->getKey()])
            ->assertOk()
            ->assertActionVisible('approve')
            ->callAction('approve')
            ->assertNotified('Approval recorded');

        $order->refresh();

        expect($order->approver_1_id)->toBe($this->user->getKey())
            ->and($order->approver_2_id)->toBeNull()
            ->and($order->status)->toBe(OrderStatus::CONFIRMED)
            ->and($order->approved_at)->toBeNull();
    });

    it('marks the order approved once the second approver confirms', function (): void {
        $firstApprover = User::factory()->create();
        grantApprovalRole($this, $firstApprover, CentralPurchasingRole::DEPUTY_DIRECTOR);
        grantApprovalRole($this, $this->user);

        $order = confirmedApprovalOrder($this, [
            'approver_1_id' => $firstApprover->getKey(),
        ]);

        livewire(ViewSupplierOrderApproval::class, ['record' => $order->getKey()])
            ->assertOk()
            ->callAction('approve')
            ->assertNotified('Order approved');

        $order->refresh();

        expect($order->status)->toBe(OrderStatus::APPROVED)
            ->and($order->approver_1_id)->toBe($firstApprover->getKey())
            ->and($order->approver_2_id)->toBe($this->user->getKey())
            ->and($order->approved_at)->not->toBeNull();
    });

    it('hides the approve action from a team member without an approval role', function (): void {
        $order = confirmedApprovalOrder($this);

        $member = User::factory()->create();
        $this->team->users()->attach($member, ['role' => 'editor']);

        $this->actingAs($member);
        Filament::setTenant($this->team);

        livewire(ViewSupplierOrderApproval::class, ['record' => $order->getKey()])
            ->assertOk()
            ->assertActionHidden('approve');

        $order->refresh();

        expect($order->approver_1_id)->toBeNull()
            ->and($order->approver_2_id)->toBeNull()
            ->and($order->status)->toBe(OrderStatus::CONFIRMED);
    });

    it('hides the approve action from a user who has already approved', function (): void {
        grantApprovalRole($this, $this->user);

        $order = confirmedApprovalOrder($this, [
            'approver_1_id' => $this->user->getKey(),
        ]);

        livewire(ViewSupplierOrderApproval::class, ['record' => $order->getKey()])
            ->assertOk()
            ->assertActionHidden('approve');

        $order->refresh();

        expect($order->approver_2_id)->toBeNull()
            ->and($order->status)->toBe(OrderStatus::CONFIRMED);
    });

    it('hides the approve action once the order is fully approved', function (): void {
        grantApprovalRole($this, $this->user);

        $approver1 = User::factory()->create();
        $approver2 = User::factory()->create();

        $order = confirmedApprovalOrder($this, [
            'status' => OrderStatus::APPROVED,
            'approver_1_id' => $approver1->getKey(),
            'approver_2_id' => $approver2->getKey(),
            'approved_at' => now(),
        ]);

        livewire(ViewSupplierOrderApproval::class, ['record' => $order->getKey()])
            ->assertOk()
            ->assertActionHidden('approve');
    });
});

describe('approve action on the list table', function (): void {
    it('records an approval via the table row action', function (): void {
        grantApprovalRole($this, $this->user);
        $order = confirmedApprovalOrder($this);

        livewire(ListSupplierOrderApprovals::class)
            ->assertOk()
            ->callAction(TestAction::make('approve')->table($order))
            ->assertNotified('Approval recorded');

        expect($order->refresh()->approver_1_id)->toBe($this->user->getKey());
    });

    it('hides the table row approve action from a member without an approval role', function (): void {
        $order = confirmedApprovalOrder($this);

        $member = User::factory()->create();
        $this->team->users()->attach($member, ['role' => 'editor']);

        $this->actingAs($member);
        Filament::setTenant($this->team);

        livewire(ListSupplierOrderApprovals::class)
            ->assertOk()
            ->assertActionHidden(TestAction::make('approve')->table($order));

        expect($order->refresh()->approver_1_id)->toBeNull();
    });
});
