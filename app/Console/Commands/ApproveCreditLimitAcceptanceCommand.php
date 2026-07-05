<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PaymentDocumentApproval;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\SupplierOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final class ApproveCreditLimitAcceptanceCommand extends Command
{
    protected $signature = 'credit-limit-acceptance:approve
                            {source : The source identifier (e.g. "PNL 0014/EL-PNL/III/2026", "PO PO-2026-0007-B", or "0014/EL-PNL/III/2026")}
                            {approver : The name of the approver (e.g., Sabrina)}
                            {--team= : Team ID or name (required)}';

    protected $description = 'Approve Credit Limit Acceptance document(s) by source (QE/PNL/PO) for testing — creates PaymentDocumentApproval and sets related record to Approved';

    public function handle(): int
    {
        $source = $this->argument('source');
        $approverName = $this->argument('approver');
        $teamInput = $this->option('team');

        if (empty($teamInput)) {
            $this->error('Option --team is required (team ID or name).');

            return self::FAILURE;
        }

        try {
            $approver = User::where('name', 'like', "%{$approverName}%")->first();
            if ($approver === null) {
                $this->error("Approver not found: {$approverName}");

                return self::FAILURE;
            }

            $team = is_numeric($teamInput)
                ? Team::find((int) $teamInput)
                : Team::where('name', 'like', "%{$teamInput}%")->first();
            if ($team === null) {
                $this->error("Team not found: {$teamInput}");

                return self::FAILURE;
            }

            $number = $this->normalizeSource($source);
            $model = $this->resolveModel($number, $team->id);
            if ($model === null) {
                $this->error("No QE, PNL, or PO found for source: {$source} (team: {$team->name})");
                $this->line('Use the exact Source from Approval > Credit Limit Acceptances (e.g. "PNL 0014/EL-PNL/III/2026" or "PO PO-2026-0007-B").');

                return self::FAILURE;
            }

            $pendingMedia = $this->getPendingMediaForModel($model, $team->id);
            if ($pendingMedia->isEmpty()) {
                $this->warn('No pending documents to approve for this source.');

                return self::SUCCESS;
            }

            foreach ($pendingMedia as $media) {
                PaymentDocumentApproval::create([
                    'team_id' => $team->id,
                    'media_id' => $media->id,
                    'user_id' => $approver->id,
                    'approved_at' => now(),
                    'notes' => 'Approved via credit-limit-acceptance:approve (test)',
                ]);
                $model->approveViaDocumentAcceptance($approver);
                $this->info("Approved document: {$media->file_name} (media id: {$media->id})");
            }

            $this->info('Credit Limit Acceptance document(s) approved successfully.');

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

    /**
     * Strip optional "QE ", "PNL ", "PO " prefix from source.
     */
    private function normalizeSource(string $source): string
    {
        $source = trim($source);
        foreach (['QE ', 'PNL ', 'PO '] as $prefix) {
            if (stripos($source, $prefix) === 0) {
                return trim(substr($source, strlen($prefix)));
            }
        }

        return $source;
    }

    /**
     * Resolve QE, PNL, or SupplierOrder by number and team.
     */
    private function resolveModel(string $number, int $teamId): QuotationEvaluation|ProfitAndLoss|SupplierOrder|null
    {
        if ($this->isQeNumber($number)) {
            return QuotationEvaluation::where('qe_number', $number)->where('team_id', $teamId)->first();
        }
        if ($this->isPnlNumber($number)) {
            return ProfitAndLoss::where('pnl_number', $number)->where('team_id', $teamId)->first();
        }
        if ($this->isPoNumber($number)) {
            return SupplierOrder::where('po_number', $number)->where('team_id', $teamId)->first();
        }

        return null;
    }

    private function isQeNumber(string $number): bool
    {
        return preg_match('/^\d+-DS\/QE\//', $number) === 1;
    }

    private function isPnlNumber(string $number): bool
    {
        return preg_match('/^\d+\/EL-PNL\//', $number) === 1;
    }

    private function isPoNumber(string $number): bool
    {
        return preg_match('/^PO-\d+/', $number) === 1;
    }

    /**
     * Get Media records in model's "documents" collection that have no PaymentDocumentApproval for this team.
     *
     * @return \Illuminate\Support\Collection<int, Media>
     */
    private function getPendingMediaForModel(QuotationEvaluation|ProfitAndLoss|SupplierOrder $model, int $teamId): \Illuminate\Support\Collection
    {
        $mediaIds = $model->getMedia('documents')->pluck('id')->toArray();
        if ($mediaIds === []) {
            return collect();
        }

        $approvedMediaIds = PaymentDocumentApproval::where('team_id', $teamId)
            ->whereIn('media_id', $mediaIds)
            ->pluck('media_id')
            ->toArray();

        $pendingIds = array_diff($mediaIds, $approvedMediaIds);

        return Media::whereIn('id', $pendingIds)->get();
    }
}
