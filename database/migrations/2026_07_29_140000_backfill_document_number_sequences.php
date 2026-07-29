<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Seeds document_number_sequences from the numbers already issued.
 *
 * The migration that creates that table leaves it empty, so on any database
 * carrying history the first allocation starts at 1 and collides with a number
 * issued under the old read-max scheme — the unique index then rejects every
 * create. Running the backfill here makes `migrate` guarantee the cutover
 * rather than leaving it to a runbook step someone has to remember.
 *
 * Safe to replay: the command never lowers a counter, and on a fresh install
 * with no documents it writes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exitCode = Artisan::call('erp:backfill-document-sequences');

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "erp:backfill-document-sequences failed with exit code {$exitCode}:\n".Artisan::output(),
            );
        }
    }

    /**
     * Counters are derived from the numbers already issued, so there is nothing
     * to restore. Dropping them would hand the next create a number that is
     * already in use, which is the bug this migration exists to prevent.
     */
    public function down(): void
    {
        //
    }
};
