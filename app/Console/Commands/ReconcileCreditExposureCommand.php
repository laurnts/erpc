<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;

/**
 * Compares the stored credit_used counter against exposure derived from
 * confirmed buyer orders.
 *
 * Runs read-only. While both exist it proves the cutover is safe; once the
 * column is dropped it becomes a standing invariant check — the derived value
 * is compared against nothing and the command reports only totals, which is the
 * point at which it should be retired or repurposed.
 */
final class ReconcileCreditExposureCommand extends Command
{
    protected $signature = 'erp:reconcile-credit-exposure {--tolerance=0.01 : Absolute difference treated as agreement}';

    protected $description = 'Report buyers whose stored credit_used disagrees with derived exposure';

    public function handle(): int
    {
        $tolerance = (float) $this->option('tolerance');
        $drifted = 0;
        $checked = 0;

        Company::query()
            ->where('is_buyer', true)
            ->withCreditExposure()
            ->chunkById(200, function ($companies) use ($tolerance, &$drifted, &$checked): void {
                foreach ($companies as $company) {
                    $checked++;
                    $stored = (float) $company->credit_used;
                    $derived = (float) $company->credit_exposure;
                    $difference = round($stored - $derived, 4);

                    if (abs($difference) <= $tolerance) {
                        continue;
                    }

                    $drifted++;
                    $this->line(sprintf(
                        'DRIFT  %s (id=%d, team=%d): stored=%.4f derived=%.4f difference=%+.4f',
                        $company->name, $company->getKey(), $company->team_id, $stored, $derived, $difference,
                    ));
                }
            }, 'companies.id', 'id');

        if ($drifted === 0) {
            $this->info(sprintf('%d buyers checked, no drift.', $checked));

            return self::SUCCESS;
        }

        $this->error(sprintf('%d of %d buyers drifted.', $drifted, $checked));

        return self::FAILURE;
    }
}
