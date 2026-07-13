<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SupplierOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

final class ApproveSupplierOrderCommand extends Command
{
    private const string TEAM_NAME = 'Central Purchasing';

    private const string APPROVER_1_NAME = 'Jun Sin';

    private const string APPROVER_2_NAME = 'Lenny';

    protected $signature = 'supplier-order:approve
                            {po_number : The PO number (e.g. PO-2026-0012)}';

    protected $description = 'Approve a supplier order (PO) for both Approver 1 (Jun Sin) and Approver 2 (Lenny) in one command (team: Central Purchasing)';

    public function handle(): int
    {
        $poNumber = $this->normalizePoNumber($this->argument('po_number'));

        try {
            $team = Team::where('name', 'like', '%'.self::TEAM_NAME.'%')->first();
            if ($team === null) {
                $this->error('Team not found: '.self::TEAM_NAME);

                return self::FAILURE;
            }

            $order = $this->resolveOrder($poNumber, $team->id);
            if (! $order instanceof \App\Models\SupplierOrder) {
                $this->error("Supplier order not found: {$poNumber} (team: {$team->name}).");

                return self::FAILURE;
            }

            if ($order->is_approved) {
                $this->info("Supplier order {$order->po_number} is already approved.");

                return self::SUCCESS;
            }

            if (! $order->status->canApprove()) {
                $this->error("Supplier order {$order->po_number} cannot be approved. Status must be Confirmed (current: {$order->status->value}).");

                return self::FAILURE;
            }

            $approver1 = User::where('name', 'like', '%'.self::APPROVER_1_NAME.'%')->first();
            $approver2 = User::where('name', 'like', '%'.self::APPROVER_2_NAME.'%')->first();
            if ($approver1 === null || $approver2 === null) {
                $this->error('Approvers not found. Required: '.self::APPROVER_1_NAME.' (Approver 1) and '.self::APPROVER_2_NAME.' (Approver 2).');

                return self::FAILURE;
            }

            if (! $order->canBeApprovedBy($approver1)) {
                $this->error("User '{$approver1->name}' cannot approve this supplier order (wrong team or role).");

                return self::FAILURE;
            }
            if (! $order->canBeApprovedBy($approver2)) {
                $this->error("User '{$approver2->name}' cannot approve this supplier order (wrong team or role).");

                return self::FAILURE;
            }

            $order->approve($approver1);
            $order->approve($approver2);
            $this->info("Supplier order {$order->po_number} approved for Approver 1 ({$approver1->name}) and Approver 2 ({$approver2->name}).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed to approve supplier order:');
            $this->line($e->getMessage());
            if ($this->option('verbose')) {
                $this->newLine();
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    private function normalizePoNumber(string $value): string
    {
        $value = trim($value);
        if (stripos($value, 'PO ') === 0) {
            return trim(substr($value, 3));
        }

        return $value;
    }

    private function resolveOrder(string $poNumber, ?int $teamId): ?SupplierOrder
    {
        $query = SupplierOrder::where('po_number', $poNumber);
        if ($teamId !== null) {
            $query->where('team_id', $teamId);
        }

        return $query->first();
    }
}
