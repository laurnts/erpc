<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;

/**
 * Reports every buyer's derived credit exposure against their credit limit
 * and flags anyone over it.
 *
 * Originally this compared the stored credit_used counter against exposure
 * derived from confirmed buyer orders, to prove the derivation was safe
 * during the transition away from hand-mutated credit columns. credit_used
 * and available_credit have since been dropped — there is nothing left to
 * compare against. Repurposed (rather than retired) because the underlying
 * check is still the invariant the whole refactor exists to protect: no
 * buyer's outstanding exposure should exceed their limit.
 */
final class ReconcileCreditExposureCommand extends Command
{
    protected $signature = 'erp:reconcile-credit-exposure';

    protected $description = 'Report buyers whose derived credit exposure exceeds their credit limit';

    public function handle(): int
    {
        $overLimit = 0;
        $checked = 0;

        Company::query()
            ->where('is_buyer', true)
            ->withCreditExposure()
            ->chunkById(200, function ($companies) use (&$overLimit, &$checked): void {
                foreach ($companies as $company) {
                    $checked++;
                    $limit = (float) $company->credit_limit;
                    $exposure = (float) $company->credit_exposure;

                    if ($exposure <= $limit) {
                        continue;
                    }

                    $overLimit++;
                    $this->line(sprintf(
                        'OVER LIMIT  %s (id=%d, team=%d): limit=%.2f exposure=%.2f over=%+.2f',
                        $company->name, $company->getKey(), $company->team_id, $limit, $exposure, $exposure - $limit,
                    ));
                }
            }, 'companies.id', 'id');

        if ($overLimit === 0) {
            $this->info(sprintf('%d buyers checked, none over their credit limit.', $checked));

            return self::SUCCESS;
        }

        $this->error(sprintf('%d of %d buyers exceed their credit limit.', $overLimit, $checked));

        return self::FAILURE;
    }
}
