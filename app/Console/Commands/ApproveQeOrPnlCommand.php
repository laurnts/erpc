<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

final class ApproveQeOrPnlCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'approve:qe-or-pnl
                            {number : The QE number (e.g., 010-DS/QE/II/2026) or PNL number (e.g., 0010/EL-PNL/II/2026)}
                            {approver : The name of the approver (e.g., Sabrina)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Approve a Quotation Evaluation (QE) or Profit & Loss (PNL) document by an approver without requiring login';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $number = $this->argument('number');
        $approverName = $this->argument('approver');

        try {
            // Determine if it's QE or PNL based on number format
            $isQE = $this->isQeNumber($number);
            $isPNL = $this->isPnlNumber($number);

            if (! $isQE && ! $isPNL) {
                $this->error("Invalid number format. Expected QE format (XXX-DS/QE/...) or PNL format (XXXX/EL-PNL/...)");
                $this->line("Got: {$number}");

                return self::FAILURE;
            }

            // Find the approver user by name
            $approver = User::where('name', 'like', "%{$approverName}%")
                ->first();

            if ($approver === null) {
                $this->error("Approver not found: {$approverName}");
                $this->line("Please check the name and try again.");

                return self::FAILURE;
            }

            // Find and approve QE
            if ($isQE) {
                return $this->approveQE($number, $approver);
            }

            // Find and approve PNL
            return $this->approvePNL($number, $approver);
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

    /**
     * Check if the number is a QE number format.
     */
    private function isQeNumber(string $number): bool
    {
        // QE format: XXX-DS/QE/... (e.g., 010-DS/QE/II/2026)
        return preg_match('/^\d+-DS\/QE\//', $number) === 1;
    }

    /**
     * Check if the number is a PNL number format.
     */
    private function isPnlNumber(string $number): bool
    {
        // PNL format: XXXX/EL-PNL/... (e.g., 0010/EL-PNL/II/2026)
        return preg_match('/^\d+\/EL-PNL\//', $number) === 1;
    }

    /**
     * Approve a Quotation Evaluation.
     */
    private function approveQE(string $qeNumber, User $approver): int
    {
        $qe = QuotationEvaluation::where('qe_number', $qeNumber)->first();

        if ($qe === null) {
            $this->error("Quotation Evaluation not found: {$qeNumber}");

            return self::FAILURE;
        }

        // Check if already approved
        if ($qe->status->isApproved()) {
            $this->warn("Quotation Evaluation {$qeNumber} is already approved.");

            return self::SUCCESS;
        }

        // Check if user can approve
        if (! $qe->canBeApprovedBy($approver)) {
            $this->error("User '{$approver->name}' cannot approve this QE.");
            $this->line("The user must be assigned as one of the approvers (Dept Head Sales, Deputy Director, or Director).");

            return self::FAILURE;
        }

        // Approve the QE
        $qe->approve($approver);

        $this->info("✅ Quotation Evaluation {$qeNumber} approved by {$approver->name}");

        // Check if fully approved
        if ($qe->fresh()->status->isApproved()) {
            $this->info("✅ All approvers have approved. QE is now fully approved.");
        } else {
            $this->line("⏳ Waiting for other approvers to complete the approval process.");
        }

        return self::SUCCESS;
    }

    /**
     * Approve a Profit & Loss document.
     */
    private function approvePNL(string $pnlNumber, User $approver): int
    {
        $pnl = ProfitAndLoss::where('pnl_number', $pnlNumber)->first();

        if ($pnl === null) {
            $this->error("Profit & Loss not found: {$pnlNumber}");

            return self::FAILURE;
        }

        // Check if already approved
        if ($pnl->status->isApproved()) {
            $this->warn("Profit & Loss {$pnlNumber} is already approved.");

            return self::SUCCESS;
        }

        // Check if user can approve
        if (! $pnl->canBeApprovedBy($approver)) {
            $this->error("User '{$approver->name}' cannot approve this PNL.");
            $this->line("The user must be assigned as one of the approvers (Dept Head Sales, Deputy Director, or Director).");

            return self::FAILURE;
        }

        // Approve the PNL
        $pnl->approve($approver);

        $this->info("✅ Profit & Loss {$pnlNumber} approved by {$approver->name}");

        // Check if fully approved
        if ($pnl->fresh()->status->isApproved()) {
            $this->info("✅ All approvers have approved. PNL is now fully approved.");
        } else {
            $this->line("⏳ Waiting for other approvers to complete the approval process.");
        }

        return self::SUCCESS;
    }
}
