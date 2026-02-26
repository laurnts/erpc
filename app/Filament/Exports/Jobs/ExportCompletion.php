<?php

declare(strict_types=1);

namespace App\Filament\Exports\Jobs;

use Filament\Actions\Exports\Jobs\ExportCompletion as FilamentExportCompletion;

/**
 * Ensures the Export model is refreshed from the database before sending the
 * completion notification. The Filament chain passes a serialized Export from
 * when the job was dispatched (successful_rows = 0). Refreshing ensures
 * download links are included when the export actually succeeded.
 */
final class ExportCompletion extends FilamentExportCompletion
{
    public function handle(): void
    {
        $this->export->refresh();

        parent::handle();
    }
}
