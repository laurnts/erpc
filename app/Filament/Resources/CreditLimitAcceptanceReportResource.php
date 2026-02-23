<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CentralPurchasingRole;
use App\Filament\Resources\CreditLimitAcceptanceReportResource\Pages\ListAcceptanceReports;
use App\Models\PaymentDocumentApproval;
use Filament\Facades\Filament;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class CreditLimitAcceptanceReportResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?int $navigationSort = 21;

    protected static string|\UnitEnum|null $navigationGroup = 'Approval';

    protected static ?string $navigationLabel = 'Acceptance Report';

    protected static ?string $pluralModelLabel = 'Acceptance Reports';

    protected static ?string $modelLabel = 'Acceptance Report';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Document Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('request_number')
                    ->label('Request Number')
                    ->getStateUsing(function (Media $record): ?string {
                        return $record->model instanceof \App\Models\Request ? $record->model->request_number : null;
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('buyer_name')
                    ->label('Buyer')
                    ->getStateUsing(function (Media $record): ?string {
                        return $record->model instanceof \App\Models\Request ? $record->model->buyer?->name : null;
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payment_terms_display')
                    ->label('Payment Terms')
                    ->getStateUsing(function (Media $record): string {
                        $paymentTerms = $record->getCustomProperty('payment_terms');
                        if ($paymentTerms) {
                            // Format: "30-100" -> "30 days - 100%"
                            $parts = explode('-', $paymentTerms);
                            if (count($parts) === 2) {
                                return "{$parts[0]} days - {$parts[1]}%";
                            }
                            return $paymentTerms;
                        }
                        return '-';
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Uploaded At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('approval_status')
                    ->label('Status')
                    ->getStateUsing(function (Media $record): string {
                        $hasApproval = PaymentDocumentApproval::where('media_id', $record->id)
                            ->where('team_id', Filament::getTenant()?->id)
                            ->exists();
                        return $hasApproval ? 'Approved' : 'Pending';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Approved' => 'success',
                        'Pending' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('approved_by')
                    ->label('Approved By')
                    ->getStateUsing(function (Media $record): ?string {
                        $approval = PaymentDocumentApproval::where('media_id', $record->id)
                            ->where('team_id', Filament::getTenant()?->id)
                            ->with('user')
                            ->first();
                        return $approval?->user?->name;
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->getStateUsing(function (Media $record): ?string {
                        $approval = PaymentDocumentApproval::where('media_id', $record->id)
                            ->where('team_id', Filament::getTenant()?->id)
                            ->first();
                        return $approval?->approved_at?->format('Y-m-d H:i:s');
                    })
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('buyer_id')
                    ->label('Buyer')
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['value'])) {
                            return $query->whereHasMorph('model', \App\Models\Request::class, function ($q) use ($data): void {
                                $q->where('buyer_id', $data['value']);
                            });
                        }

                        return $query;
                    })
                    ->options(function (): array {
                        /** @var \App\Models\Team|null $team */
                        $team = Filament::getTenant();
                        
                        if ($team === null) {
                            return [];
                        }

                        return \App\Models\Company::query()
                            ->where('team_id', $team->id)
                            ->where('is_buyer', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('approval_status')
                    ->label('Approval Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['value'])) {
                            $teamId = Filament::getTenant()?->id;
                            $approvedMediaIds = PaymentDocumentApproval::where('team_id', $teamId)
                                ->pluck('media_id')
                                ->toArray();
                            
                            if ($data['value'] === 'pending') {
                                return $query->whereNotIn('id', $approvedMediaIds);
                            } elseif ($data['value'] === 'approved') {
                                return $query->whereIn('id', $approvedMediaIds);
                            }
                        }

                        return $query;
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('view')
                        ->label('View Document')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn (Media $record): string => $record->getUrl())
                        ->openUrlInNewTab(),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Media $record): bool => static::canApprove($record))
                        ->requiresConfirmation()
                        ->form([
                            \Filament\Forms\Components\Textarea::make('notes')
                                ->label('Notes')
                                ->rows(3),
                        ])
                        ->action(function (Media $record, array $data): void {
                            $team = Filament::getTenant();
                            $user = Auth::user();

                            PaymentDocumentApproval::create([
                                'team_id' => $team->id,
                                'media_id' => $record->id,
                                'user_id' => $user->id,
                                'approved_at' => now(),
                                'notes' => $data['notes'] ?? null,
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->title('Document approved')
                                ->body('Payment document has been approved successfully.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcceptanceReports::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'file_name',
        ];
    }

    /**
     * @return Builder<Media>
     */
    public static function getEloquentQuery(): Builder
    {
        $team = Filament::getTenant();

        // Build query without parent tenant filtering since Media doesn't have team relationship
        $query = Media::query()
            ->where('collection_name', 'completion_reports')
            ->where('custom_properties->is_payment_document', true)
            ->whereHasMorph('model', \App\Models\Request::class, function ($query) use ($team): void {
                $query->where('team_id', $team?->getKey());
            })
            ->with(['model.buyer', 'model']);

        return $query;
    }

    /**
     * Approve button only for central purchasing finance team members (with is_approver).
     */
    protected static function canApprove(Media $record): bool
    {
        $user = Auth::user();
        $team = Filament::getTenant();

        if (! $user || ! $team) {
            return false;
        }

        $hasPermission = $user->teams()
            ->where('teams.id', $team->id)
            ->where('team_user.role', 'central_purchasing')
            ->where('team_user.central_purchasing_role', CentralPurchasingRole::FINANCE->value)
            ->where('team_user.is_approver', true)
            ->exists();

        if (! $hasPermission) {
            return false;
        }

        $hasApproval = PaymentDocumentApproval::where('media_id', $record->id)
            ->where('team_id', $team->id)
            ->exists();

        return ! $hasApproval;
    }
}
