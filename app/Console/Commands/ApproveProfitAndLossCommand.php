<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesDocumentApprover;
use App\Models\ProfitAndLoss;
use Illuminate\Console\Command;
use Throwable;

final class ApproveProfitAndLossCommand extends Command
{
    use ResolvesDocumentApprover;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'profit-and-loss:approve
                            {number : The PNL number (e.g., 0010/EL-PNL/II/2026)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fully approve a Profit & Loss (PNL) document (all 3 approvers) without requiring login';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $number = $this->argument('number');

        try {
            $pnl = ProfitAndLoss::where('pnl_number', $number)->first();

            if ($pnl === null) {
                $this->error("Profit & Loss not found: {$number}");

                return self::FAILURE;
            }

            if ($pnl->status->isApproved()) {
                $this->warn("Profit & Loss {$number} is already approved.");

                return self::SUCCESS;
            }

            $approver = $this->resolveApproverUser($pnl);
            if (! $approver instanceof \App\Models\User) {
                $this->error("No approvers assigned to Profit & Loss {$number}.");

                return self::FAILURE;
            }

            $pnl->approveViaDocumentAcceptance($approver);

            $this->info("✅ Profit & Loss {$number} fully approved (all 3 approvers).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed to approve:');
            $this->line($e->getMessage());

            if ($this->option('verbose')) {
                $this->newLine();
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }
}
