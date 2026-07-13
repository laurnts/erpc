<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Actions\Media\AttachUploadedFiles;
use App\Enums\RequestStage;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Models\GoodsReceiveBatch;
use App\Models\PaymentDocumentApproval;
use App\Models\Request;
use App\Support\DocumentUpload;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

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

                        if ($first instanceof \Spatie\MediaLibrary\MediaCollections\Models\Media && $count === 1) {
                            return $first->name;
                        }
                        if ($count > 1) {
                            return $first instanceof \Spatie\MediaLibrary\MediaCollections\Models\Media ? $first->name.' (+'.($count - 1).' more)' : $count.' documents';
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
                    ->modalSubmitActionLabel('Submit')
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
                                    ->mapWithKeys(fn ($order): array => [
                                        $order->id => "{$order->po_number} - ".($order->supplier?->name ?? ''),
                                    ])
                                    ->toArray();
                            })
                            ->required()
                            ->preload(),
                        FileUpload::make('document')
                            ->label('Documents')
                            ->helperText(DocumentUpload::helperText(10240, notes: ['All files appear as one row']))
                            ->multiple()
                            ->acceptedFileTypes(DocumentUpload::ACCEPTED_MIME_TYPES)
                            ->disk('local')
                            ->directory(Request::GOODS_RECEIVE_UPLOAD_DIRECTORY)
                            ->visibility('private')
                            ->downloadable()
                            ->openable()
                            ->previewable()
                            ->required()
                            ->maxSize(10240)
                            ->validationMessages([
                                'max' => DocumentUpload::maxSizeMessage(10240),
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

                        $files = $data['document'] ?? null;
                        $files = is_array($files) ? $files : ($files !== null ? [$files] : []);

                        $attached = app(AttachUploadedFiles::class)->execute(
                            $request,
                            $files,
                            'goods_receive',
                            Request::GOODS_RECEIVE_UPLOAD_DIRECTORY,
                            [
                                'uploaded_by' => $userId,
                                'supplier_order_id' => $supplierOrderId,
                            ],
                        );

                        $mediaIds = [];
                        $name = $data['name'] ?? null;
                        foreach ($attached as $media) {
                            // Preserve the pre-convergence naming: custom name when given,
                            // otherwise the file's basename (extension included).
                            $media->update(['name' => is_string($name) && $name !== '' ? $name : $media->file_name]);
                            $mediaIds[] = $media->id;
                        }

                        if ($mediaIds === []) {
                            Notification::make()
                                ->title('Upload failed')
                                ->body('No document was uploaded. Please select at least one file.')
                                ->danger()
                                ->send();
                            throw new \Filament\Support\Exceptions\Halt;
                        }

                        return GoodsReceiveBatch::create([
                            'request_id' => $request->id,
                            'supplier_order_id' => $supplierOrderId,
                            'user_id' => $userId,
                            'media_ids' => $mediaIds,
                        ]);
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
