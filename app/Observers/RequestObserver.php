<?php

declare(strict_types=1);

namespace App\Observers;

use App\Data\TeamErpSettings;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;

final readonly class RequestObserver
{
    /**
     * Handle the Request "creating" event.
     */
    public function creating(Request $request): void
    {
        // Only set team_id and creator_id if not already set
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($request->creator_id === null) {
                $request->creator_id = $user->getKey();
            }

            if ($request->team_id === null && $user->currentTeam !== null) {
                $request->team_id = $user->currentTeam->getKey();
            }
        }

        // Auto-generate request number if not provided
        /** @var string|null $requestNumber */
        $requestNumber = $request->request_number;
        if ($requestNumber === null || $requestNumber === '') {
            $request->request_number = $this->generateRequestNumber($request);
        }
    }

    /**
     * Generate a unique request number (REQ-YYYY-NNNN format).
     */
    private function generateRequestNumber(Request $request): string
    {
        $team = $request->team ?? ($request->team_id !== null ? Team::find($request->team_id) : null);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->request_number_prefix;

        $year = date('Y');
        $pattern = $prefix.'-'.$year.'-%';

        // Get the highest sequence number for this team and year
        $lastRequest = Request::query()
            ->where('team_id', $request->team_id)
            ->where('request_number', 'like', $pattern)
            ->orderByDesc('request_number')
            ->first();

        $nextNumber = 1;
        if ($lastRequest !== null) {
            $regex = '/^'.preg_quote($prefix, '/').'-'.$year.'-(\d+)$/';
            if (preg_match($regex, (string) $lastRequest->request_number, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);
    }
}
