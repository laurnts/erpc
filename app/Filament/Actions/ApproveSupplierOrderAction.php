<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Enums\OrderStatus;
use App\Models\SupplierOrder;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Records one of the two required approvals on a confirmed supplier order.
 * Shared between the Supplier Order Approvals resource and the Supplier
 * Orders tab on the request page so approvers can act from either place.
 */
final class ApproveSupplierOrderAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'approve';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Approve')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(function (?SupplierOrder $record): bool {
                if ($record === null) {
                    return false;
                }

                if ($record->status !== OrderStatus::CONFIRMED) {
                    return false;
                }

                $user = auth()->user();
                if (! $user instanceof User) {
                    return false;
                }

                if (! $record->canBeApprovedBy($user)) {
                    return false;
                }

                return $record->approver_1_id !== $user->id && $record->approver_2_id !== $user->id;
            })
            ->requiresConfirmation()
            ->modalHeading('Approve this supplier order?')
            ->modalDescription(function (SupplierOrder $record): string {
                $description = 'This order requires approval from at least 2 approvers. ';

                if (self::recordedApprovals($record) === 0) {
                    return $description.'You will be the first approver. One more approval is needed.';
                }

                return $description.'One approval has been received. Your approval will complete the approval process and the order will be ready to send.';
            })
            ->action(function (SupplierOrder $record): void {
                $user = auth()->user();
                if (! $user instanceof User) {
                    return;
                }

                try {
                    $record->approve($user);

                    if (self::recordedApprovals($record) === 2) {
                        Notification::make()
                            ->title('Order approved')
                            ->body('Order has been fully approved and is now ready to send to supplier.')
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Approval recorded')
                        ->body('Your approval has been recorded. One more approval is needed.')
                        ->success()
                        ->send();
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot approve')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * How many of the two approval slots are filled.
     */
    private static function recordedApprovals(SupplierOrder $record): int
    {
        return ($record->approver_1_id !== null ? 1 : 0)
            + ($record->approver_2_id !== null ? 1 : 0);
    }
}
