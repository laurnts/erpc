<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CentralPurchasingRole;
use App\Filament\Resources\GoodsReceiveApprovalResource\Pages\ListGoodsReceiveApprovals;
use App\Models\GoodsReceiveBatch;
use App\Models\PaymentDocumentApproval;
use Filament\Facades\Filament;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class GoodsReceiveApprovalResource extends Resource
{
    protected static ?string $model = GoodsReceiveBatch::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 22;

    protected static string|\UnitEnum|null $navigationGroup = 'Approval';

    protected static ?string $navigationLabel = 'Goods Receive';

    protected static ?string $pluralModelLabel = 'Goods Receive';

    protected static ?string $modelLabel = 'Goods Receive Document';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('File Name')
                    ->getStateUsing(function (GoodsReceiveBatch $record): string {
                        $first = $record->getFirstMedia();
                        $count = count($record->media_ids ?? []);

                        if ($first !== null && $count === 1) {
                            return $first->name;
                        }
                        if ($count > 1 && $first !== null) {
                            return $first->name.' (+'.($count - 1).' more)';
                        }
                        if ($count > 1) {
                            return $count.' documents';
                        }

                        return '-';
                    })
                    ->weight('bold')
                    ->searchable(false)
                    ->sortable(false),
                TextColumn::make('request.request_number')
                    ->label('Request Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplierOrder.supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplierOrder.po_number')
                    ->label('Supplier Order Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('document_count')
                    ->label('Document Count')
                    ->getStateUsing(fn (GoodsReceiveBatch $record): int => count($record->media_ids ?? []))
                    ->sortable(false),
                TextColumn::make('user.name')
                    ->label('Upload By')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Upload At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('approval_status')
                    ->label('Status')
                    ->getStateUsing(function (GoodsReceiveBatch $record): string {
                        $teamId = Filament::getTenant()?->id;
                        if ($teamId === null || empty($record->media_ids)) {
                            return 'Pending';
                        }
                        $approvedCount = PaymentDocumentApproval::query()
                            ->where('team_id', $teamId)
                            ->whereIn('media_id', $record->media_ids)
                            ->pluck('media_id')
                            ->unique()
                            ->count();
                        $total = count($record->media_ids);

                        return $approvedCount >= $total ? 'Approved' : 'Pending';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Approved' => 'success',
                        'Pending' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(false),
                TextColumn::make('approved_by')
                    ->label('Approved By')
                    ->getStateUsing(function (GoodsReceiveBatch $record): ?string {
                        $teamId = Filament::getTenant()?->id;
                        if ($teamId === null || empty($record->media_ids)) {
                            return null;
                        }
                        $approval = PaymentDocumentApproval::query()
                            ->where('team_id', $teamId)
                            ->whereIn('media_id', $record->media_ids)
                            ->with('user')
                            ->first();

                        return $approval?->user?->name;
                    })
                    ->searchable(false)
                    ->sortable(false),
                TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->getStateUsing(function (GoodsReceiveBatch $record): ?string {
                        $teamId = Filament::getTenant()?->id;
                        if ($teamId === null || empty($record->media_ids)) {
                            return null;
                        }
                        $approval = PaymentDocumentApproval::query()
                            ->where('team_id', $teamId)
                            ->whereIn('media_id', $record->media_ids)
                            ->first();

                        return $approval?->approved_at?->format('Y-m-d H:i:s');
                    })
                    ->dateTime()
                    ->sortable(false),
            ])
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['value'])) {
                            $orderIds = \App\Models\SupplierOrder::where('supplier_id', $data['value'])->pluck('id')->toArray();
                            if ($orderIds !== []) {
                                return $query->whereIn('supplier_order_id', $orderIds);
                            }
                        }

                        return $query;
                    })
                    ->options(function (): array {
                        $team = Filament::getTenant();
                        if ($team === null) {
                            return [];
                        }

                        return \App\Models\Company::query()
                            ->where('team_id', $team->id)
                            ->where('is_supplier', true)
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
                        if (empty($data['value'])) {
                            return $query;
                        }
                        $teamId = Filament::getTenant()?->id;
                        if ($teamId === null) {
                            return $query;
                        }
                        $approvedMediaIds = PaymentDocumentApproval::where('team_id', $teamId)
                            ->pluck('media_id')
                            ->toArray();

                        if ($data['value'] === 'pending') {
                            return $query->whereRaw(
                                '(SELECT COUNT(DISTINCT p.media_id) FROM payment_document_approvals p WHERE p.team_id = ? AND JSON_CONTAINS(goods_receive_batches.media_ids, CAST(p.media_id AS JSON), \'$\')) < JSON_LENGTH(goods_receive_batches.media_ids)',
                                [$teamId]
                            );
                        }
                        if ($data['value'] === 'approved') {
                            return $query->whereRaw(
                                '(SELECT COUNT(DISTINCT p.media_id) FROM payment_document_approvals p WHERE p.team_id = ? AND JSON_CONTAINS(goods_receive_batches.media_ids, CAST(p.media_id AS JSON), \'$\')) >= JSON_LENGTH(goods_receive_batches.media_ids)',
                                [$teamId]
                            )->whereRaw('JSON_LENGTH(goods_receive_batches.media_ids) > 0');
                        }

                        return $query;
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('view_documents')
                        ->label('View documents')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->slideOver()
                        ->modalHeading('Goods Receive Documents')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->form(fn (GoodsReceiveBatch $record): array => [
                            Section::make('Goods Receive Documents')
                                ->schema([
                                    ViewField::make('goods_receive_doc_list')
                                        ->label('')
                                        ->view('filament.forms.components.goods-receive-document-list'),
                                ]),
                        ])
                        ->visible(fn (GoodsReceiveBatch $record): bool => $record->getMediaRecords()->isNotEmpty()),
                    DeleteAction::make()
                        ->label('Delete')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->authorize(fn (GoodsReceiveBatch $record): bool => true)
                        ->action(function (GoodsReceiveBatch $record): void {
                            foreach ($record->getMediaRecords() as $media) {
                                $media->delete();
                            }
                            $record->delete();

                            \Filament\Notifications\Notification::make()
                                ->title('Documents deleted')
                                ->body('Goods receive document batch has been deleted.')
                                ->success()
                                ->send();
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (GoodsReceiveBatch $record): bool => static::canApproveBatch($record))
                        ->requiresConfirmation()
                        ->form([
                            \Filament\Forms\Components\Textarea::make('notes')
                                ->label('Notes')
                                ->rows(3),
                        ])
                        ->action(function (GoodsReceiveBatch $record, array $data): void {
                            $team = Filament::getTenant();
                            $user = Auth::user();
                            if (! $team || ! $user) {
                                return;
                            }
                            $approvedIds = PaymentDocumentApproval::where('team_id', $team->id)
                                ->whereIn('media_id', $record->media_ids ?? [])
                                ->pluck('media_id')
                                ->toArray();
                            foreach ($record->media_ids ?? [] as $mediaId) {
                                if (in_array($mediaId, $approvedIds, true)) {
                                    continue;
                                }
                                PaymentDocumentApproval::create([
                                    'team_id' => $team->id,
                                    'media_id' => $mediaId,
                                    'user_id' => $user->id,
                                    'approved_at' => now(),
                                    'notes' => $data['notes'] ?? null,
                                ]);
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Documents approved')
                                ->body('All documents in this batch have been approved.')
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
            'index' => ListGoodsReceiveApprovals::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    /**
     * @return Builder<GoodsReceiveBatch>
     */
    public static function getEloquentQuery(): Builder
    {
        $team = Filament::getTenant();

        return GoodsReceiveBatch::query()
            ->whereHas('request', function (Builder $q) use ($team): void {
                $q->where('team_id', $team?->getKey());
            })
            ->with(['request', 'supplierOrder.supplier', 'user']);
    }

    protected static function canApproveBatch(GoodsReceiveBatch $record): bool
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

        $approvedCount = PaymentDocumentApproval::where('team_id', $team->id)
            ->whereIn('media_id', $record->media_ids ?? [])
            ->pluck('media_id')
            ->unique()
            ->count();
        $total = count($record->media_ids ?? []);

        return $total > 0 && $approvedCount < $total;
    }
}
