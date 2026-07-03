<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\FetchFaviconForCompany;
use App\Models\Company;
use App\Models\User;

final readonly class CompanyObserver
{
    /**
     * Handle the Company "creating" event.
     */
    public function creating(Company $company): void
    {
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            // Only set creator_id if not already set (e.g., by factory)
            if ($company->creator_id === null) {
                $company->creator_id = $user->getKey();
            }

            // Only set team_id if not already set (e.g., by factory) and user has a current team
            if ($company->team_id === null && $user->currentTeam !== null) {
                $company->team_id = $user->currentTeam->getKey();
            }
        }

        // Auto-generate company code if not provided
        if ($company->code === null || $company->code === '') {
            $company->code = Company::generateNextCode($company->team_id);
        }
    }

    /**
     * Handle the Company "updating" event.
     *
     * The approved credit limit is governed by the credit limit request approval workflow.
     * Any attempt to change it outside that workflow is rejected so the audit ledger
     * (BuyerCreditUsageHistory) can never be bypassed.
     */
    public function updating(Company $company): void
    {
        if ($company->isDirty('credit_limit') && ! Company::creditLimitChangeAuthorized()) {
            throw new \RuntimeException(
                'Credit limit can only be changed through the credit limit request approval workflow.'
            );
        }
    }

    /**
     * Handle the Company "created" event.
     */
    public function created(Company $company): void
    {
        FetchFaviconForCompany::dispatch($company)->afterCommit();
    }

    /**
     * Handle the Company "saved" event.
     * Invalidate AI summary when company data changes.
     */
    public function saved(Company $company): void
    {
        $company->invalidateAiSummary();
    }
}
