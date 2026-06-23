<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Erp\GenerateSupplierQuotesForRequest;
use App\Data\TeamErpSettings;
use App\Enums\RequestStage;
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
            ->withTrashed()
            ->where('team_id', $request->team_id)
            ->where('request_number', 'like', $pattern)
            ->orderByDesc('request_number')
            ->first();

        $nextNumber = 1;
        if ($lastRequest !== null) {
            $regex = '/^'.preg_quote((string) $prefix, '/').'-'.$year.'-(\d+)$/';
            if (preg_match($regex, (string) $lastRequest->request_number, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);
    }

    /**
     * Handle the Request "updated" event.
     *
     * When a request transitions to AWAITING_SUPPLIER_RESPONSE,
     * automatically generate supplier quotes for all suppliers
     * of the articles in the request.
     */
    public function updated(Request $request): void
    {
        // Check if stage changed to AWAITING_SUPPLIER_RESPONSE
        if (! $request->wasChanged('stage')) {
            return;
        }

        $originalStage = $request->getOriginal('stage');
        $previousStage = $originalStage instanceof RequestStage
            ? $originalStage
            : RequestStage::tryFrom((string) $originalStage);

        // Only generate quotes when transitioning FROM DRAFT to AWAITING_SUPPLIER_RESPONSE
        if ($previousStage !== RequestStage::DRAFT) {
            return;
        }

        if ($request->stage !== RequestStage::AWAITING_SUPPLIER_RESPONSE) {
            return;
        }

        // Check if quotes already exist for this request to avoid duplicates
        if ($request->supplierQuotes()->exists()) {
            return;
        }

        // Generate supplier quotes
        $action = new GenerateSupplierQuotesForRequest;
        $action->execute($request);
    }
}
