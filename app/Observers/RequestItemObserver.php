<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\PNLStatus;
use App\Models\ProfitAndLoss;
use App\Models\RequestItem;

final readonly class RequestItemObserver
{
    /**
     * Handle the RequestItem "created" event.
     */
    public function created(RequestItem $requestItem): void
    {
        // When a new item is added to a request, reset approved PNLs to pending
        // This ensures PNL needs re-approval when additional items are added
        if ($requestItem->request_id === null) {
            return;
        }

        $profitAndLosses = ProfitAndLoss::query()
            ->where('request_id', $requestItem->request_id)
            ->where('status', PNLStatus::APPROVED)
            ->get();

        foreach ($profitAndLosses as $pnl) {
            $pnl->status = PNLStatus::NEED_APPROVAL;
            $pnl->dept_head_sales_approved_at = null;
            $pnl->deputy_director_approved_at = null;
            $pnl->director_approved_at = null;
            $pnl->saveQuietly();
        }
    }
}
