<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierOrderApprovals\Pages;

use App\Actions\Media\AttachUploadedFiles;
use App\Enums\OrderStatus;
use App\Filament\Resources\RequestResource;
use App\Filament\Resources\SupplierOrderApprovals\Schemas\SupplierOrderApprovalInfolist;
use App\Filament\Resources\SupplierOrderApprovals\SupplierOrderApprovalResource;
use App\Models\SupplierOrder;
use App\Models\User;
use App\Services\Erp\PdfGenerationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

final class ViewSupplierOrderApproval extends ViewRecord
{
    protected static string $resource = SupplierOrderApprovalResource::class;

    public function infolist(Schema $schema): Schema
    {
        return SupplierOrderApprovalInfolist::configure($schema);
    }

    protected function mutateInfolistData(array $data): array
    {
        // Ensure relationships are loaded before infolist renders
        /** @var SupplierOrder $record */
        $record = $this->getRecord();
        $record->loadMissing([
            'supplier',
            'request.buyer.keyAccounts',
            'approver1',
            'approver2',
            'items',
            'currency',
        ]);

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load necessary relationships
        /** @var SupplierOrder $record */
        $record = $this->getRecord();
        $record->load([
            'supplier',
            'request.buyer.keyAccounts',
            'approver1',
            'approver2',
            'items',
            'currency',
        ]);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (): string => RequestResource::getUrl('view', ['record' => $this->getRecord()->request_id]))
                    ->openUrlInNewTab(false),
                Action::make('downloadPdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        /** @var SupplierOrder $record */
                        $record = $this->getRecord();
                        $service = app(PdfGenerationService::class);
                        $pdf = $service->generateSupplierOrderPdf($record);
                        $filename = $service->getSupplierOrderFilename($record);
                        $content = $pdf->output();

                        return response()->streamDownload(
                            callback: static function () use ($content): void {
                                echo $content;
                            },
                            name: $filename,
                            headers: ['Content-Type' => 'application/pdf'],
                        );
                    }),
                Action::make('uploadDocument')
                    ->label('Upload Document')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        FileUpload::make('document')
                            ->label('Document')
                            ->required()
                            ->disk('local')
                            ->directory(SupplierOrder::DOCUMENTS_UPLOAD_DIRECTORY)
                            ->visibility('private')
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
                            ->maxSize(10240),
                        TextInput::make('name')
                            ->label('Document Name')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        /** @var SupplierOrder $record */
                        $record = $this->getRecord();
                        $file = $data['document'] ?? null;
                        if (is_array($file)) {
                            $file = $file[0] ?? null;
                        }
                        if ($file && is_string($file)) {
                            app(AttachUploadedFiles::class)->execute($record, [$file], 'documents', SupplierOrder::DOCUMENTS_UPLOAD_DIRECTORY);
                            $record->refresh();

                            $name = $data['name'] ?? null;
                            if (is_string($name) && $name !== '') {
                                $record->getMedia('documents')->last()?->update(['name' => $name]);
                            }

                            Notification::make()
                                ->title('Document uploaded')
                                ->success()
                                ->send();
                            $this->refresh();
                        }
                    }),
            ]),
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(function (): bool {
                    /** @var SupplierOrder $record */
                    $record = $this->getRecord();

                    // Must be in confirmed status
                    if ($record->status !== OrderStatus::CONFIRMED) {
                        return false;
                    }

                    // User must be authenticated
                    $user = auth()->user();
                    if ($user === null) {
                        return false;
                    }

                    // Check if user can approve
                    if (! $record->canBeApprovedBy($user)) {
                        return false;
                    }

                    // Check if user already approved
                    if ($record->approver_1_id === $user->id || $record->approver_2_id === $user->id) {
                        return false;
                    }

                    return true;
                })
                ->requiresConfirmation()
                ->modalHeading('Approve this supplier order?')
                ->modalDescription(function (): string {
                    /** @var SupplierOrder $record */
                    $record = $this->getRecord();

                    $approverCount = 0;
                    if ($record->approver_1_id !== null) {
                        $approverCount++;
                    }
                    if ($record->approver_2_id !== null) {
                        $approverCount++;
                    }

                    $description = 'This order requires approval from at least 2 approvers. ';

                    if ($approverCount === 0) {
                        $description .= 'You will be the first approver. One more approval is needed.';
                    } elseif ($approverCount === 1) {
                        $description .= 'One approval has been received. Your approval will complete the approval process and the order will be ready to send.';
                    }

                    return $description;
                })
                ->action(function (): void {
                    /** @var SupplierOrder $record */
                    $record = $this->getRecord();
                    /** @var User $user */
                    $user = auth()->user();

                    try {
                        $record->approve($user);

                        // Refresh the record to get updated data
                        $record->refresh();

                        $approverCount = 0;
                        if ($record->approver_1_id !== null) {
                            $approverCount++;
                        }
                        if ($record->approver_2_id !== null) {
                            $approverCount++;
                        }

                        if ($approverCount === 2) {
                            Notification::make()
                                ->title('Order approved')
                                ->body('Order has been fully approved and is now ready to send to supplier.')
                                ->success()
                                ->send();

                            // Redirect back to list if fully approved (order is now APPROVED, won't show in approval list)
                            $this->redirect(SupplierOrderApprovalResource::getUrl('index'));
                        } else {
                            Notification::make()
                                ->title('Approval recorded')
                                ->body('Your approval has been recorded. One more approval is needed.')
                                ->success()
                                ->send();

                            // Refresh the current page to show updated approval status
                            $this->refresh();
                        }
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Cannot approve')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
