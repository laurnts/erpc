<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesDocumentApprover;
use App\Models\QuotationEvaluation;
use Illuminate\Console\Command;
use Throwable;

final class ApproveQuotationEvaluationCommand extends Command
{
    use ResolvesDocumentApprover;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quotation-evaluation:approve
                            {number : The QE number (e.g., 010-DS/QE/II/2026)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fully approve a Quotation Evaluation (QE) document (all 3 approvers) without requiring login';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $number = $this->argument('number');

        try {
            $qe = QuotationEvaluation::where('qe_number', $number)->first();

            if ($qe === null) {
                $this->error("Quotation Evaluation not found: {$number}");

                return self::FAILURE;
            }

            if ($qe->status->isApproved()) {
                $this->warn("Quotation Evaluation {$number} is already approved.");

                return self::SUCCESS;
            }

            $approver = $this->resolveApproverUser($qe);
            if (! $approver instanceof \App\Models\User) {
                $this->error("No approvers assigned to Quotation Evaluation {$number}.");

                return self::FAILURE;
            }

            $qe->approveViaDocumentAcceptance($approver);

            $this->info("✅ Quotation Evaluation {$number} fully approved (all 3 approvers).");

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
