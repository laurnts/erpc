<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerResource\Pages;

use App\Enums\CentralPurchasingRole;
use App\Enums\CreditLimitRequestStatus;
use App\Filament\Resources\BuyerResource;
use App\Mail\Erp\CreditLimitIncreaseRequestMail;
use App\Models\BuyerCreditLimitRequest;
use App\Services\Email\EmailTemplateService;
use App\Services\TeamMemberService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;

final class ViewBuyer extends ViewRecord
{
    protected static string $resource = BuyerResource::class;

    protected function getHeaderActions(): array
    {
        /** @var \App\Models\Company $buyer */
        $buyer = $this->record;
        $pendingRequest = $buyer->pendingCreditLimitRequest();
        $hasPendingRequest = $pendingRequest !== null;
        $hasRequestedLimit = $buyer->requested_credit_limit !== null;

        return [
            ActionGroup::make([
                EditAction::make()
                    ->slideOver()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Store requested_credit_limit for afterSave processing
                        $this->pendingRequestedLimit = isset($data['requested_credit_limit']) && $data['requested_credit_limit'] !== null 
                            ? (string) $data['requested_credit_limit'] 
                            : null;
                        
                        // Remove it from data so it doesn't get saved directly
                        unset($data['requested_credit_limit']);
                        
                        return $data;
                    })
                    ->after(function (\App\Models\Company $record, array $data): void {
                        // Handle credit limit request if requested_credit_limit was provided
                        if (isset($this->pendingRequestedLimit) && $this->pendingRequestedLimit !== null) {
                            $this->handleCreditLimitRequest($record, $this->pendingRequestedLimit);
                        }
                    }),
                Action::make('request_credit_increase')
                    ->label('Request Credit Limit Increase')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('primary')
                    ->visible(fn (): bool => ! $hasPendingRequest && ! $hasRequestedLimit)
                    ->modalHeading('Request Credit Limit Increase')
                    ->modalWidth('md')
                    ->form([
                        TextInput::make('requested_limit')
                            ->label('Requested Credit Limit')
                            ->numeric()
                            ->required()
                            ->minValue(fn (): float => (float) $buyer->credit_limit + 0.01)
                            ->prefix(function (): string {
                                /** @var \App\Models\Team|null $team */
                                $team = Filament::getTenant();
                                $currency = $team?->getBaseCurrency();

                                return $currency?->symbol_position === 'before' ? ($currency->symbol ?? '$') : '';
                            })
                            ->suffix(function (): string {
                                /** @var \App\Models\Team|null $team */
                                $team = Filament::getTenant();
                                $currency = $team?->getBaseCurrency();

                                return $currency?->symbol_position === 'after' ? ($currency->symbol ?? '') : '';
                            })
                            ->helperText(fn (): string => "Current active limit: " . number_format((float) $buyer->credit_limit, 2)),
                    ])
                    ->action(function (array $data): void {
                        /** @var \App\Models\Company $buyer */
                        $buyer = $this->record;
                        /** @var \App\Models\Team $team */
                        $team = Filament::getTenant();

                        $requestedLimit = (string) $data['requested_limit'];
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
                    }),
                DeleteAction::make(),
            ]),
        ];
    }

    /**
     * Store requested_credit_limit value before save for processing in after callback.
     */
    private ?string $pendingRequestedLimit = null;

    /**
     * Handle credit limit request creation after edit form is saved.
     */
    private function handleCreditLimitRequest(\App\Models\Company $buyer, string $requestedLimit): void
    {
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
