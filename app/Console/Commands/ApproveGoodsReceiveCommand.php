<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GoodsReceiveBatch;
use App\Models\PaymentDocumentApproval;
use App\Models\SupplierOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

final class ApproveGoodsReceiveCommand extends Command
{
    private const TEAM_NAME = 'Central Purchasing';

    private const APPROVER_NAME = 'Jun Sin';

    protected $signature = 'goods-receive:approve
                            {identifier : The PO number (e.g. PO-2026-0012-C) or Goods Receive batch ID}';

    protected $description = 'Approve goods receive document(s) for the given PO or batch (team: Central Purchasing, approver: Jun Sin)';

    public function handle(): int
    {
        $identifier = trim((string) $this->argument('identifier'));

        try {
            $team = Team::where('name', 'like', '%'.self::TEAM_NAME.'%')->first();
            if ($team === null) {
                $this->error('Team not found: '.self::TEAM_NAME);

                return self::FAILURE;
            }

            $approver = User::where('name', 'like', '%'.self::APPROVER_NAME.'%')->first();
            if ($approver === null) {
                $this->error('Approver not found: '.self::APPROVER_NAME);

                return self::FAILURE;
            }

            $batches = $this->resolveBatches($identifier, $team->id);
            if ($batches->isEmpty()) {
                $this->error("No pending goods receive batch found for: {$identifier} (team: {$team->name}).");

                return self::FAILURE;
            }

            foreach ($batches as $batch) {
                $created = $this->approveBatch($batch, $team->id, $approver->id);
                if ($created > 0) {
                    $poNumber = $batch->supplierOrder?->po_number ?? '?';
                    $this->info("Approved {$created} document(s) for batch id {$batch->id} (PO: {$poNumber}).");
                }
            }

            $this->info('Goods receive document(s) approved successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed to approve goods receive:');
            $this->line($e->getMessage());
            if ($this->option('verbose')) {
                $this->newLine();
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, GoodsReceiveBatch>
     */
    private function resolveBatches(string $identifier, int $teamId): \Illuminate\Support\Collection
    {
        if (is_numeric($identifier)) {
            $batch = GoodsReceiveBatch::query()
                ->where('id', (int) $identifier)
                ->whereHas('request', fn ($q) => $q->where('team_id', $teamId))
                ->with(['supplierOrder', 'request'])
                ->first();
            if ($batch !== null && $this->batchHasPendingApprovals($batch, $teamId)) {
                return collect([$batch]);
            }

            return collect();
        }

        $poNumber = $this->normalizePoNumber($identifier);
        $order = SupplierOrder::where('po_number', $poNumber)->where('team_id', $teamId)->first();
        if ($order === null) {
            return collect();
        }

        return GoodsReceiveBatch::query()
            ->where('supplier_order_id', $order->id)
            ->whereHas('request', fn ($q) => $q->where('team_id', $teamId))
            ->with(['supplierOrder', 'request'])
            ->get()
            ->filter(fn (GoodsReceiveBatch $b) => $this->batchHasPendingApprovals($b, $teamId))
            ->values();
    }

    private function batchHasPendingApprovals(GoodsReceiveBatch $batch, int $teamId): bool
    {
        $mediaIds = $batch->media_ids ?? [];
        if ($mediaIds === []) {
            return false;
        }
        $approvedIds = PaymentDocumentApproval::query()
            ->where('team_id', $teamId)
            ->whereIn('media_id', $mediaIds)
            ->pluck('media_id')
            ->unique()
            ->count();

        return $approvedIds < count($mediaIds);
    }

    private function approveBatch(GoodsReceiveBatch $batch, int $teamId, int $approverId): int
    {
        $mediaIds = $batch->media_ids ?? [];
        if ($mediaIds === []) {
            return 0;
        }
        $existingIds = PaymentDocumentApproval::query()
            ->where('team_id', $teamId)
            ->whereIn('media_id', $mediaIds)
            ->pluck('media_id')
            ->all();
        $created = 0;
        foreach ($mediaIds as $mediaId) {
            if (in_array($mediaId, $existingIds, true)) {
                continue;
            }
            PaymentDocumentApproval::create([
                'team_id' => $teamId,
                'media_id' => $mediaId,
                'user_id' => $approverId,
                'approved_at' => now(),
                'notes' => 'Approved via goods-receive:approve command',
            ]);
            $created++;
        }

        return $created;
    }

    private function normalizePoNumber(string $value): string
    {
        $value = trim($value);
        if (stripos($value, 'PO ') === 0) {
            return trim(substr($value, 3));
        }

        return $value;
    }
}
