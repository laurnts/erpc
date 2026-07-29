<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\BuyerPortal\NotifyPortalUsers;
use App\Actions\Erp\GenerateSupplierQuotesForRequest;
use App\Data\TeamErpSettings;
use App\Enums\RequestStage;
use App\Models\Request;
use App\Models\Team;
use App\Models\User;
use App\Notifications\PortalRequestStageChangedNotification;
use App\Services\Erp\Numbering\DocumentNumberAllocator;
use Illuminate\Validation\ValidationException;

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
     *
     * @see \App\Services\Erp\Numbering\DocumentNumberAllocator for why this is a
     *      counter row rather than a read-max over existing numbers.
     */
    private function generateRequestNumber(Request $request): string
    {
        $team = $request->team ?? ($request->team_id !== null ? Team::find($request->team_id) : null);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->request_number_prefix;

        $year = date('Y');
        $sequence = app(DocumentNumberAllocator::class)
            ->next((int) $request->team_id, 'request', $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }

    /**
     * Handle the Request "updating" event.
     *
     * Hard-gates the Completed stage on derived fulfillment. This is the
     * single enforcement point that every code path updating a request's
     * stage goes through (including the ViewRequest EditAction modal, which
     * does not call Request::transitionTo()); transitionTo() shares the same
     * check via Request::completionFulfillmentError() so there is one source
     * of truth for the rule.
     *
     * @throws ValidationException
     */
    public function updating(Request $request): void
    {
        if (! $request->isDirty('stage') || $request->stage !== RequestStage::COMPLETED) {
            return;
        }

        $error = $request->completionFulfillmentError();

        if ($error !== null) {
            throw ValidationException::withMessages(['stage' => $error]);
        }
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
        if ($request->wasChanged('stage')) {
            $this->notifyPortalUsersOfStageChange($request, $request->getOriginal('stage'));
        }

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

    private function notifyPortalUsersOfStageChange(Request $request, mixed $previousStage): void
    {
        if ($request->buyer_id === null) {
            return;
        }

        $previous = $previousStage instanceof RequestStage
            ? $previousStage
            : RequestStage::tryFrom((string) $previousStage);

        if ($previous === RequestStage::PREPARING_BUYER_QUOTE
            && $request->stage === RequestStage::AWAITING_BUYER_CONFIRMATION) {
            return;
        }

        app(NotifyPortalUsers::class)->forRequest(
            $request,
            new PortalRequestStageChangedNotification($request),
        );
    }
}
