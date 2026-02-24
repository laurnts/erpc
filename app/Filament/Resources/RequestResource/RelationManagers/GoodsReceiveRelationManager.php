<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\RequestStage;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Models\GoodsReceiveBatch;
use App\Models\PaymentDocumentApproval;
use App\Models\Request;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class GoodsReceiveRelationManager extends RelationManager
{
    use HasRequestStageTab;

    protected static string $relationship = 'goodsReceiveBatches';

    protected static ?string $title = 'Goods Receive';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-archive-box';

    protected static function getAssociatedStage(): RequestStage
    {
        return RequestStage::GOODS_RECEIVE;
    }

    protected static function getBaseTabTitle(): string
    {
        return 'Goods Receive';
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['supplierOrder.supplier', 'user']))
            ->recordAction(null)
            ->recordUrl(null)
            ->columns([
                TextColumn::make('display_name')
                    ->label('File Name')
                    ->getStateUsing(function (GoodsReceiveBatch $record): string {
                        $first = $record->getFirstMedia();
                        $count = count($record->media_ids ?? []);

                        if ($first !== null && $count === 1) {
                            return $first->name;
                        }
                        if ($count > 1) {
                            return $first !== null ? $first->name.' (+'.($count - 1).' more)' : $count.' documents';
                        }

                        return '-';
                    })
                    ->searchable(false)
                    ->sortable(false),
                TextColumn::make('supplier_order_display')
                    ->label('Supplier Order (PO)')
                    ->getStateUsing(fn (GoodsReceiveBatch $record): string => $record->supplierOrder
                        ? "{$record->supplierOrder->po_number} ({$record->supplierOrder->supplier?->name})"
                        : '-')
                    ->searchable(false)
                    ->sortable(false),
                TextColumn::make('approval_status')
                    ->label('Status')
                    ->getStateUsing(function (GoodsReceiveBatch $record): string {
                        $request = $record->request;
                        if ($request->team_id === null || empty($record->media_ids)) {
                            return 'Pending';
                        }
                        $approvedCount = PaymentDocumentApproval::query()
                            ->where('team_id', $request->team_id)
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
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Upload Document')
                    ->modalHeading('Goods Receive Documents')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Select::make('supplier_order_id')
                            ->label('Supplier Order (PO)')
                            ->options(function (): array {
                                /** @var Request $request */
                                $request = $this->getOwnerRecord();
                                return $request->supplierOrders()
                                    ->with('supplier')
                                    ->orderBy('po_number')
                                    ->get()
                                    ->mapWithKeys(fn ($order) => [
                                        $order->id => "{$order->po_number} - ".($order->supplier?->name ?? ''),
                                    ])
                                    ->toArray();
                            })
                            ->required()
                            ->preload(),
                        FileUpload::make('document')
                            ->label('Documents')
                            ->helperText('Upload one or more goods receive documents (PDF, Excel, Word, Images). All files will appear as one row.')
                            ->hint('Maximum file size per file: 10MB')
                            ->hintColor('warning')
                            ->multiple()
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'image/png',
                                'image/jpeg',
                                'image/jpg',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->disk('local')
                            ->directory('goods-receive')
                            ->visibility('private')
                            ->downloadable()
                            ->openable()
                            ->previewable()
                            ->required()
                            ->maxSize(10240)
                            ->validationMessages([
                                'max' => 'Each file must not exceed 10MB.',
                            ]),
                        TextInput::make('name')
                            ->label('Document Name (optional)')
                            ->helperText('Optional: Used as base name when uploading a single file')
                            ->maxLength(255),
                    ])
                    ->using(function (array $data): GoodsReceiveBatch {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();
                        $userId = Auth::id();
                        $supplierOrderId = (int) $data['supplier_order_id'];
                        $mediaIds = [];

                        $addFiles = function (array|string $files) use ($request, $userId, $supplierOrderId, $data, &$mediaIds): void {
                            $list = is_array($files) ? $files : [$files];
                            foreach ($list as $file) {
                                if (! is_string($file)) {
                                    continue;
                                }
                                $filePath = storage_path('app/'.ltrim($file, '/'));
                                if (! file_exists($filePath)) {
                                    continue;
                                }
                                $name = $data['name'] ?? basename($filePath);
                                $media = $request->addMedia($filePath)
                                    ->usingName($name)
                                    ->toMediaCollection('goods_receive');
                                $media->setCustomProperty('uploaded_by', $userId);
                                $media->setCustomProperty('supplier_order_id', $supplierOrderId);
                                $media->save();
                                $mediaIds[] = $media->id;
                            }
                        };

                        if (isset($data['document']) && is_array($data['document']) && ! empty($data['document'])) {
                            $addFiles($data['document']);
                        } elseif (isset($data['document']) && is_string($data['document'])) {
                            $addFiles($data['document']);
                        }

                        if ($mediaIds === []) {
                            Notification::make()
                                ->title('Upload failed')
                                ->body('No document was uploaded. Please select at least one file.')
                                ->danger()
                                ->send();
                            throw new \Filament\Support\Exceptions\Halt();
                        }

                        $batch = GoodsReceiveBatch::create([
                            'request_id' => $request->id,
                            'supplier_order_id' => $supplierOrderId,
                            'user_id' => $userId,
                            'media_ids' => $mediaIds,
                        ]);

                        return $batch;
                    })
                    ->after(function (): void {
                        Notification::make()
                            ->title('Documents uploaded')
                            ->body('Goods receive document(s) have been uploaded successfully.')
                            ->success()
                            ->send();
                    })
                    ->successNotificationTitle('Documents uploaded successfully'),
            ])
            ->recordActions([
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
                        ->authorize(fn ($record): bool => true)
                        ->action(function (GoodsReceiveBatch $record): void {
                            foreach ($record->getMediaRecords() as $media) {
                                $media->delete();
                            }
                            $record->delete();
                            Notification::make()
                                ->title('Documents deleted')
                                ->body('Goods receive document batch has been deleted.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->bulkActions([
                //
            ])
            ->emptyStateHeading('No goods receive documents uploaded')
            ->emptyStateDescription('Upload goods receive documents. All documents must be approved before you can proceed to Inbound Shipments.')
            ->emptyStateIcon('heroicon-o-archive-box');
    }
}
