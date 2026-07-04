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
                            {number : The QE number (e.g., 010-DS/QE/II/2026) or PNL number (e.g., 0010/EL-PNL/II/2026)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fully approve a Quotation Evaluation (QE) or Profit & Loss (PNL) document (all 3 approvers) without requiring login';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $number = $this->argument('number');

        try {
            // Determine if it's QE or PNL based on number format
            $isQE = $this->isQeNumber($number);
            $isPNL = $this->isPnlNumber($number);

            if (! $isQE && ! $isPNL) {
                $this->error("Invalid number format. Expected QE format (XXX-DS/QE/...) or PNL format (XXXX/EL-PNL/...)");
                $this->line("Got: {$number}");

                return self::FAILURE;
            }

            // Find and approve QE
            if ($isQE) {
                return $this->approveQE($number);
            }

            // Find and approve PNL
            return $this->approvePNL($number);
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
     * Approve a Quotation Evaluation with all approvers.
     */
    private function approveQE(string $qeNumber): int
    {
        $qe = QuotationEvaluation::where('qe_number', $qeNumber)->first();

        if ($qe === null) {
            $this->error("Quotation Evaluation not found: {$qeNumber}");

            return self::FAILURE;
        }

        if ($qe->status->isApproved()) {
            $this->warn("Quotation Evaluation {$qeNumber} is already approved.");

            return self::SUCCESS;
        }

        $approver = $this->resolveApproverUser($qe);
        if ($approver === null) {
            $this->error("No approvers assigned to Quotation Evaluation {$qeNumber}.");

            return self::FAILURE;
        }

        $qe->approveViaDocumentAcceptance($approver);

        $this->info("✅ Quotation Evaluation {$qeNumber} fully approved (all 3 approvers).");

        return self::SUCCESS;
    }

    /**
     * Approve a Profit & Loss document with all approvers.
     */
    private function approvePNL(string $pnlNumber): int
    {
        $pnl = ProfitAndLoss::where('pnl_number', $pnlNumber)->first();

        if ($pnl === null) {
            $this->error("Profit & Loss not found: {$pnlNumber}");

            return self::FAILURE;
        }

        if ($pnl->status->isApproved()) {
            $this->warn("Profit & Loss {$pnlNumber} is already approved.");

            return self::SUCCESS;
        }

        $approver = $this->resolveApproverUser($pnl);
        if ($approver === null) {
            $this->error("No approvers assigned to Profit & Loss {$pnlNumber}.");

            return self::FAILURE;
        }

        $pnl->approveViaDocumentAcceptance($approver);

        $this->info("✅ Profit & Loss {$pnlNumber} fully approved (all 3 approvers).");

        return self::SUCCESS;
    }

    /**
     * Resolve any assigned approver user from a QE or PNL record.
     */
    private function resolveApproverUser(QuotationEvaluation|ProfitAndLoss $model): ?User
    {
        foreach ([$model->dept_head_sales_id, $model->deputy_director_id, $model->approved_by_id] as $userId) {
            if ($userId === null) {
                continue;
            }

            $user = User::find($userId);
            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }
}
