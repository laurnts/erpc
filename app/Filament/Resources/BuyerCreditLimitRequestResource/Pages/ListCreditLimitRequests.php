<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerCreditLimitRequestResource\Pages;

use App\Enums\CreditLimitRequestStatus;
use App\Filament\Resources\BuyerCreditLimitRequestResource;
use App\Mail\Erp\CreditLimitIncreaseRequestMail;
use App\Models\BuyerCreditLimitRequest;
use App\Models\Company;
use App\Services\Email\EmailTemplateService;
use App\Services\TeamMemberService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Log;

final class ListCreditLimitRequests extends ListRecords
{
    protected static string $resource = BuyerCreditLimitRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('request_credit_increase')
                ->label('Request Credit Limit')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('primary')
                ->modalHeading('Request Credit Limit')
                ->modalWidth('md')
                ->form([
                    Select::make('buyer_id')
                        ->label('Buyer')
                        ->options(function (): array {
                            /** @var \App\Models\Team|null $team */
                            $team = Filament::getTenant();

                            if ($team === null) {
                                return [];
                            }

                            return Company::query()
                                ->where('team_id', $team->id)
                                ->where('is_buyer', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            if ($state) {
                                $buyer = Company::find($state);
                                if ($buyer) {
                                    $set('current_limit', (string) $buyer->credit_limit);
                                    $set('requested_limit', null);
                                }
                            } else {
                                $set('current_limit', null);
                                $set('requested_limit', null);
                            }
                        }),
                    TextInput::make('current_limit')
                        ->label('Max Credit Limit')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false)
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
                        ->visible(fn (Get $get): bool => $get('buyer_id') !== null),
                    TextInput::make('requested_limit')
                        ->label('Requested Credit Limit')
                        ->numeric()
                        ->required()
                        ->minValue(0)
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
                        ->helperText(function (Get $get): string {
                            $currentLimit = $get('current_limit');
                            if ($currentLimit) {
                                return 'Current Credit Limit: '.number_format((float) $currentLimit, 2).'. You can request an increase or decrease (minimum: 0).';
                            }

                            return 'Select a buyer first to see the Current Credit Limit.';
                        })
                        ->visible(fn (Get $get): bool => $get('buyer_id') !== null),
                ])
                ->action(function (array $data): void {
                    /** @var \App\Models\Team|null $team */
                    $team = Filament::getTenant();

                    if ($team === null) {
                        Notification::make()
                            ->title('Error')
                            ->body('Unable to determine team context.')
                            ->danger()
                            ->send();

                        return;
                    }

                    /** @var \App\Models\Company|null $buyer */
                    $buyer = Company::find($data['buyer_id']);

                    if ($buyer === null) {
                        Notification::make()
                            ->title('Error')
                            ->body('Buyer not found.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $requestedLimit = (string) $data['requested_limit'];
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
                }),
        ];
    }
}
