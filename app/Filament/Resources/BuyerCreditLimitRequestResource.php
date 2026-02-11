<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CentralPurchasingRole;
use App\Enums\CreditLimitRequestStatus;
use App\Filament\Resources\BuyerCreditLimitRequestResource\Pages\ListCreditLimitRequests;
use App\Models\BuyerCreditLimitRequest;
use App\Services\TeamMemberService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class BuyerCreditLimitRequestResource extends Resource
{
    protected static ?string $model = BuyerCreditLimitRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 20;

    protected static string|\UnitEnum|null $navigationGroup = 'Approval';

    protected static ?string $navigationLabel = 'Credit Limit';

    protected static ?string $pluralModelLabel = 'Credit Limit Requests';

    protected static ?string $modelLabel = 'Credit Limit Request';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('buyer.code')
                    ->label('Code')
                    
                    ->sortable(),
                TextColumn::make('current_limit')
                    ->label('Max Credit Limit')
                    ->money(fn (): string => Filament::getTenant() instanceof \App\Models\Team ? Filament::getTenant()->getBaseCurrencyCode() : 'USD')
                    ->sortable(),
                TextColumn::make('requested_limit')
                    ->label('Requested Limit')
                    ->money(fn (): string => Filament::getTenant() instanceof \App\Models\Team ? Filament::getTenant()->getBaseCurrencyCode() : 'USD')
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),
                TextColumn::make('increase_amount')
                    ->label('Changes')
                    ->getStateUsing(fn (BuyerCreditLimitRequest $record): float => (float) $record->requested_limit - (float) $record->current_limit)
                    ->money(fn (): string => Filament::getTenant() instanceof \App\Models\Team ? Filament::getTenant()->getBaseCurrencyCode() : 'USD')
                    ->color('success'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('approval_count')
                    ->label('Approvals')
                    ->getStateUsing(fn (BuyerCreditLimitRequest $record): string => $record->approvalCount() . '/2')
                    ->badge()
                    ->color(fn (BuyerCreditLimitRequest $record): string => $record->approvalCount() >= 2 ? 'success' : 'warning'),
                TextColumn::make('approvers.name')
                    ->label('Approved By')
                    ->badge()
                    ->separator(','),
                TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Requested At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(CreditLimitRequestStatus::class)
                    ->multiple(),
                SelectFilter::make('buyer_id')
                    ->label('Buyer')
                    ->relationship('buyer', 'name')
                    
                    ->preload(),
                SelectFilter::make('requested_by_id')
                    ->label('Requested By')
                    ->relationship('requestedBy', 'name')
                    
                    ->preload(),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (BuyerCreditLimitRequest $record): bool => $record->canBeApprovedBy(auth()->user()))
                    ->requiresConfirmation()
                    ->modalHeading('Approve Credit Limit Request')
                    ->modalDescription(fn (BuyerCreditLimitRequest $record): string => "Are you sure you want to approve the credit limit request for {$record->buyer->name}?")
                    ->form([
                        Textarea::make('notes')
                            ->label('Notes (Optional)')
                            ->rows(3)
                            ->placeholder('Add any notes about this approval...'),
                    ])
                    ->action(function (BuyerCreditLimitRequest $record, array $data): void {
                        try {
                            $record->approve(auth()->user(), $data['notes'] ?? null);

                            Notification::make()
                                ->title('Request Approved')
                                ->body($record->isApproved()
                                    ? 'Credit limit has been updated. The request is now fully approved.'
                                    : 'Your approval has been recorded. One more approval is needed.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Approval Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (BuyerCreditLimitRequest $record): bool => $record->canBeRejectedBy(auth()->user()))
                    ->requiresConfirmation()
                    ->modalHeading('Reject Credit Limit Request')
                    ->modalDescription(fn (BuyerCreditLimitRequest $record): string => "Are you sure you want to reject the credit limit request for {$record->buyer->name}?")
                    ->form([
                        Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3)
                            ->placeholder('Please provide a reason for rejection...'),
                    ])
                    ->action(function (BuyerCreditLimitRequest $record, array $data): void {
                        try {
                            $record->reject(auth()->user(), $data['reason']);

                            Notification::make()
                                ->title('Request Rejected')
                                ->body('The credit limit request has been rejected.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Rejection Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('view_approval_notes')
                    ->label('Approval Notes')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn (BuyerCreditLimitRequest $record): bool => $record->approvalCount() > 0)
                    ->modalHeading('Approval Notes')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function (BuyerCreditLimitRequest $record): \Illuminate\Contracts\View\View {
                        $approvals = $record->approvals()->with('user')->orderBy('approved_at', 'desc')->get();
                        
                        return view('filament.resources.buyer-credit-limit-request-resource.approval-notes-modal', [
                            'approvals' => $approvals,
                        ]);
                    })
                    ->modalWidth('lg'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditLimitRequests::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['buyer.name', 'buyer.code'];
    }

    /**
     * @return Builder<BuyerCreditLimitRequest>
     */
    public static function getEloquentQuery(): Builder
    {
        $team = Filament::getTenant();

        return parent::getEloquentQuery()
            ->where('team_id', $team?->getKey())
            ->with(['buyer', 'requestedBy', 'approvers', 'approvals.user']);
    }
}
