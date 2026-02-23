<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CentralPurchasingRole;
use App\Filament\Resources\CreditLimitAcceptanceReportResource\Pages\ListAcceptanceReports;
use App\Filament\Resources\ProfitAndLossResource;
use App\Filament\Resources\QuotationEvaluationResource;
use App\Filament\Resources\RequestResource;
use App\Filament\Resources\SupplierOrderApprovals\SupplierOrderApprovalResource;
use App\Models\PaymentDocumentApproval;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierOrder;
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
            ->recordUrl(null)
            ->recordAction(null)
            ->columns([
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
                TextColumn::make('source')
                    ->label('Source')
                    ->getStateUsing(function (Media $record): string {
                        $model = $record->model;
                        if ($model instanceof QuotationEvaluation) {
                            return 'QE ' . $model->qe_number;
                        }
                        if ($model instanceof ProfitAndLoss) {
                            return 'PNL ' . $model->pnl_number;
                        }
                        if ($model instanceof SupplierOrder) {
                            return 'PO ' . $model->po_number;
                        }
                        return 'Payment Document';
                    })
                    ->url(function (Media $record): ?string {
                        $model = $record->model;
                        if ($model instanceof QuotationEvaluation) {
                            return QuotationEvaluationResource::getUrl('view', ['record' => $model]);
                        }
                        if ($model instanceof ProfitAndLoss) {
                            return ProfitAndLossResource::getUrl('view', ['record' => $model]);
                        }
                        if ($model instanceof SupplierOrder) {
                            return SupplierOrderApprovalResource::getUrl('view', ['record' => $model]);
                        }
                        return null;
                    })
                    ->badge()
                    ->color(function (Media $record): string {
                        if ($record->collection_name !== 'documents') {
                            return 'gray';
                        }
                        return match ($record->model_type) {
                            'quotation_evaluation' => 'info',
                            'profit_and_loss' => 'warning',
                            'supplier_order' => 'success',
                            default => 'gray',
                        };
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('request_number')
                    ->label('Request Number')
                    ->getStateUsing(function (Media $record): ?string {
                        $model = $record->model;
                        if ($model instanceof Request) {
                            return $model->request_number;
                        }
                        if ($model instanceof QuotationEvaluation || $model instanceof ProfitAndLoss || $model instanceof SupplierOrder) {
                            return $model->request?->request_number;
                        }
                        return null;
                    })
                    ->url(function (Media $record): ?string {
                        $model = $record->model;
                        if ($model instanceof Request) {
                            return RequestResource::getUrl('view', ['record' => $model]);
                        }
                        if ($model instanceof QuotationEvaluation) {
                            return QuotationEvaluationResource::getUrl('view', ['record' => $model]);
                        }
                        if ($model instanceof ProfitAndLoss) {
                            return ProfitAndLossResource::getUrl('view', ['record' => $model]);
                        }
                        if ($model instanceof SupplierOrder) {
                            return SupplierOrderApprovalResource::getUrl('view', ['record' => $model]);
                        }
                        return null;
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('buyer_name')
                    ->label('Buyer')
                    ->getStateUsing(function (Media $record): ?string {
                        $model = $record->model;
                        if ($model instanceof Request) {
                            return $model->buyer?->name;
                        }
                        if ($model instanceof QuotationEvaluation || $model instanceof ProfitAndLoss || $model instanceof SupplierOrder) {
                            return $model->request?->buyer?->name;
                        }
                        return null;
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payment_terms_display')
                    ->label('Payment Terms')
                    ->getStateUsing(function (Media $record): string {
                        if ($record->collection_name !== 'completion_reports') {
                            return '—';
                        }
                        $paymentTerms = $record->getCustomProperty('payment_terms');
                        if ($paymentTerms) {
                            // Format: "30-100" -> "30 days - 100%"
                            $parts = explode('-', $paymentTerms);
                            if (count($parts) === 2) {
                                return "{$parts[0]} days - {$parts[1]}%";
                            }
                            return $paymentTerms;
                        }
                        return '—';
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Uploaded At')
                    ->dateTime()
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
                        if (empty($data['value'])) {
                            return $query;
                        }
                        $buyerId = $data['value'];
                        return $query->where(function (Builder $q) use ($buyerId): void {
                            $q->whereHasMorph('model', Request::class, fn (Builder $m): Builder => $m->where('buyer_id', $buyerId))
                                ->orWhereHasMorph('model', [QuotationEvaluation::class, ProfitAndLoss::class, SupplierOrder::class], fn (Builder $m): Builder => $m->whereHas('request', fn (Builder $r): Builder => $r->where('buyer_id', $buyerId)));
                        });
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

                            $model = $record->model;
                            if ($model instanceof QuotationEvaluation || $model instanceof ProfitAndLoss || $model instanceof SupplierOrder) {
                                $model->approveViaDocumentAcceptance($user);
                            }

                            $message = $model instanceof QuotationEvaluation || $model instanceof ProfitAndLoss || $model instanceof SupplierOrder
                                ? 'Document approved. Related record has been set to Approved.'
                                : 'Payment document has been approved successfully.';

                            \Filament\Notifications\Notification::make()
                                ->title('Document approved')
                                ->body($message)
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

        return Media::query()
            ->where(function (Builder $q) use ($team): void {
                $q->where(function (Builder $sub) use ($team): void {
                    $sub->where('collection_name', 'completion_reports')
                        ->where('custom_properties->is_payment_document', true)
                        ->whereHasMorph('model', Request::class, fn (Builder $m): Builder => $m->where('team_id', $team?->getKey()));
                })->orWhere(function (Builder $sub) use ($team): void {
                    $sub->where('collection_name', 'documents')
                        ->whereHasMorph('model', [QuotationEvaluation::class, ProfitAndLoss::class, SupplierOrder::class], fn (Builder $m): Builder => $m->where('team_id', $team?->getKey()));
                });
            })
            ->with(['model']);
    }

    /**
     * Approve: key account for QE/PNL/Supplier Order documents; finance approver for payment documents.
     */
    protected static function canApprove(Media $record): bool
    {
        $user = Auth::user();
        $team = Filament::getTenant();

        if (! $user || ! $team) {
            return false;
        }

        $hasApproval = PaymentDocumentApproval::where('media_id', $record->id)
            ->where('team_id', $team->id)
            ->exists();

        if ($hasApproval) {
            return false;
        }

        if ($record->collection_name === 'documents' && in_array($record->model_type, ['quotation_evaluation', 'profit_and_loss', 'supplier_order'], true)) {
            return $user->teams()
                ->where('teams.id', $team->id)
                ->where('team_user.role', 'central_purchasing')
                ->where('team_user.central_purchasing_role', CentralPurchasingRole::KEY_ACCOUNT->value)
                ->exists();
        }

        return $user->teams()
            ->where('teams.id', $team->id)
            ->where('team_user.role', 'central_purchasing')
            ->where('team_user.central_purchasing_role', CentralPurchasingRole::FINANCE->value)
            ->where('team_user.is_approver', true)
            ->exists();
    }
}
