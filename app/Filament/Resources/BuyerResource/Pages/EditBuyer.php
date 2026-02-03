<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerResource\Pages;

use App\Enums\CreditLimitRequestStatus;
use App\Filament\Resources\BuyerResource;
use App\Mail\Erp\CreditLimitIncreaseRequestMail;
use App\Models\BuyerCreditLimitRequest;
use App\Services\Email\EmailTemplateService;
use App\Services\TeamMemberService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

final class EditBuyer extends EditRecord
{
    protected static string $resource = BuyerResource::class;

    /**
     * Store requested_credit_limit value before save for processing in afterSave.
     */
    private ?string $pendingRequestedLimit = null;

    /**
     * Store original requested_credit_limit to detect changes.
     */
    private ?string $originalRequestedLimit = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var \App\Models\Company $buyer */
        $buyer = $this->record;
        
        // Store original value for comparison
        $this->originalRequestedLimit = $buyer->requested_credit_limit;
        
        // Store requested_credit_limit for afterSave processing
        $this->pendingRequestedLimit = isset($data['requested_credit_limit']) && $data['requested_credit_limit'] !== null 
            ? (string) $data['requested_credit_limit'] 
            : null;
        
        // Remove it from data so it doesn't get saved directly (we'll handle it in afterSave)
        unset($data['requested_credit_limit']);
        
        return $data;
    }

    protected function afterSave(): void
    {
        /** @var \App\Models\Company $buyer */
        $buyer = $this->record;
        
        // If requested_credit_limit was cleared (changed from value to null)
        if ($this->originalRequestedLimit !== null && $this->pendingRequestedLimit === null) {
            // Clear the requested_credit_limit field
            $buyer->requested_credit_limit = null;
            $buyer->save();
            return;
        }
        
        // If requested_credit_limit was provided and changed
        if ($this->pendingRequestedLimit !== null) {
            // Check if it's a new request or update
            $isNewRequest = $this->originalRequestedLimit === null || 
                           $this->originalRequestedLimit !== $this->pendingRequestedLimit;
            
            if ($isNewRequest) {
                $this->createCreditLimitRequest($this->pendingRequestedLimit);
            } else {
                // Value didn't change, just save it
                $buyer->requested_credit_limit = $this->pendingRequestedLimit;
                $buyer->save();
            }
        }
    }

    private function createCreditLimitRequest(string $requestedLimit): void
    {
        /** @var \App\Models\Company $buyer */
        $buyer = $this->record;
        /** @var \App\Models\Team $team */
        $team = Filament::getTenant();
        
        if ($team === null) {
            Notification::make()
                ->title('Error')
                ->body('Unable to determine team context.')
                ->danger()
                ->send();
            return;
        }

        $currentLimit = (string) $buyer->credit_limit;

        // Validate requested limit is greater than current
        if ((float) $requestedLimit <= (float) $currentLimit) {
            Notification::make()
                ->title('Invalid Request')
                ->body('Requested credit limit must be greater than the current active limit.')
                ->danger()
                ->send();

            return;
        }

        // Check for existing pending request
        $pendingRequest = $buyer->pendingCreditLimitRequest();
        if ($pendingRequest !== null) {
            Notification::make()
                ->title('Request Already Exists')
                ->body('A pending credit limit increase request already exists for this buyer.')
                ->warning()
                ->send();

            return;
        }

        try {
            // Create credit limit request
            $request = BuyerCreditLimitRequest::create([
                'team_id' => $team->id,
                'buyer_id' => $buyer->id,
                'current_limit' => $currentLimit,
                'requested_limit' => $requestedLimit,
                'status' => CreditLimitRequestStatus::PENDING,
                'requested_by_id' => auth()->id(),
            ]);

            // Set requested_credit_limit on buyer
            $buyer->requested_credit_limit = $requestedLimit;
            $buyer->save();

            // Send email notification to finance approvers
            try {
                $financeApprovers = TeamMemberService::getFinanceApprovers($team);

                if ($financeApprovers->isNotEmpty()) {
                    $emailService = app(EmailTemplateService::class);
                    $emails = $financeApprovers->pluck('email')->filter()->toArray();

                    if (! empty($emails)) {
                        foreach ($emails as $email) {
                            $emailService->sendWithTeamSettings(
                                $team,
                                new CreditLimitIncreaseRequestMail($request),
                                $email
                            );
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send credit limit increase request email', [
                    'request_id' => $request->id,
                    'error' => $e->getMessage(),
                ]);
            }

            Notification::make()
                ->title('Request Submitted')
                ->body('Credit limit increase request has been submitted. Finance approvers have been notified.')
                ->success()
                ->send();
            
            // Refresh the record to update the form state
            $this->record->refresh();
        } catch (\Exception $e) {
            Log::error('Failed to create credit limit request', [
                'buyer_id' => $buyer->id,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Request Failed')
                ->body('Failed to create credit limit increase request. Please try again.')
                ->danger()
                ->send();
        }
    }
}
