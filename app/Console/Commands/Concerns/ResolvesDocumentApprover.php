<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\User;

trait ResolvesDocumentApprover
{
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
