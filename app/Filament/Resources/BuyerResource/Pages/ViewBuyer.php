<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerResource\Pages;

use App\Actions\Portal\InvitePortalUser;
use App\Enums\PortalType;
use App\Filament\Resources\BuyerResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

final class ViewBuyer extends ViewRecord
{
    protected static string $resource = BuyerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invitePortalUser')
                ->label('Invite Portal User')
                ->icon('heroicon-o-envelope')
                ->color('primary')
                ->visible(fn (): bool => (bool) config('app.customer_portal_enabled', true))
                ->schema([
                    TextInput::make('name')
                        ->label('Contact Name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data, \App\Models\Company $record): void {
                    /** @var \App\Models\Team $team */
                    $team = Filament::getTenant();

                    app(InvitePortalUser::class)->execute(
                        team: $team,
                        company: $record,
                        portal: PortalType::Customer,
                        email: $data['email'],
                        name: $data['name'],
                        invitedBy: auth()->user(),
                    );

                    Notification::make()
                        ->title('Invitation sent')
                        ->body('Portal invitation email has been sent to '.$data['email'])
                        ->success()
                        ->send();
                }),
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

        // Validate requested limit is not negative
        if ((float) $requestedLimit < 0) {
            Notification::make()
                ->title('Invalid Request')
                ->body('Requested credit limit cannot be negative.')
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
