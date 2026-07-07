<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Actions\Media\AttachUploadedFiles;
use App\Enums\OrderStatus;
use App\Enums\RequestStage;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Models\BuyerOrder;
use App\Models\PaymentDocumentApproval;
use App\Models\Request;
use App\Support\DocumentUpload;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class CompletionReportsRelationManager extends RelationManager
{
    use HasRequestStageTab;

    protected static string $relationship = 'media';

    protected static ?string $title = 'Completion Report';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-check';

    protected static function getAssociatedStage(): RequestStage
    {
        return RequestStage::DELIVERED;
    }

    protected static function getBaseTabTitle(): string
    {
        return 'Completion Report';
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->where('collection_name', 'completion_reports');
            })
            ->recordAction(null)
            ->recordUrl(null)
            ->columns([
                TextColumn::make('name')
                    ->label('File Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_payment_document')
                    ->label('Mark')
                    ->getStateUsing(fn (Media $record): string => (bool) $record->getCustomProperty('is_payment_document', false) ? 'Payment' : '-')
                    ->sortable(false),
                TextColumn::make('approval_status')
                    ->label('Status')
                    ->getStateUsing(function (Media $record): string {
                        if (! (bool) $record->getCustomProperty('is_payment_document', false)) {
                            return '-';
                        }
                        $request = $record->model;
                        if (! $request instanceof Request || $request->team_id === null) {
                            return '-';
                        }
                        $approved = PaymentDocumentApproval::query()
                            ->where('media_id', $record->id)
                            ->where('team_id', $request->team_id)
                            ->exists();

                        return $approved ? 'Approved' : 'Pending';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Approved' => 'success',
                        'Pending' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(false),
                TextColumn::make('mime_type')
                    ->label('Type')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Uploaded At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Upload Document')
                    ->modalHeading('Upload Document')
                    ->modalSubmitActionLabel('Submit')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        FileUpload::make('document')
                            ->label('Document')
                            ->helperText(DocumentUpload::helperText(10240))
                            ->acceptedFileTypes(DocumentUpload::ACCEPTED_MIME_TYPES)
                            ->disk('local')
                            ->directory(Request::COMPLETION_REPORTS_UPLOAD_DIRECTORY)
                            ->visibility('private')
                            ->downloadable()
                            ->openable()
                            ->previewable()
                            ->required()
                            ->maxSize(10240) // 10MB in KB
                            ->validationMessages([
                                'max' => DocumentUpload::maxSizeMessage(10240),
                            ]),
                        TextInput::make('name')
                            ->label('Document Name')
                            ->helperText('Optional: Provide a descriptive name for this document')
                            ->maxLength(255),
                        Checkbox::make('is_payment_document')
                            ->label('Mark as payment document')
                            ->reactive(),
                        Select::make('payment_terms')
                            ->label('Payment Terms')
                            ->options(fn (): array => $this->getPaymentTermsOptions())
                            ->visible(fn ($get): bool => $get('is_payment_document') === true)
                            ->required(fn ($get): bool => $get('is_payment_document') === true)
                            ->disabled(fn (): bool => empty($this->getPaymentTermsOptions()))
                            ->rules([
                                function (): \Closure {
                                    return function (string $attribute, $value, \Closure $fail): void {
                                        if (empty($value)) {
                                            return;
                                        }
                                        /** @var Request $request */
                                        $request = $this->getOwnerRecord();
                                        $existingMedia = $request->getMedia('completion_reports')
                                            ->filter(fn (Media $m): bool => (bool) $m->getCustomProperty('is_payment_document', false)
                                                && $m->getCustomProperty('payment_terms') === $value);
                                        if ($existingMedia->isEmpty()) {
                                            return;
                                        }
                                        $mediaIds = $existingMedia->pluck('id')->toArray();
                                        $alreadyApproved = $request->team_id !== null && PaymentDocumentApproval::query()
                                            ->whereIn('media_id', $mediaIds)
                                            ->where('team_id', $request->team_id)
                                            ->exists();
                                        if ($alreadyApproved) {
                                            $fail('A payment document for the selected payment terms has already been approved. Please choose different payment terms or do not mark as payment document.');
                                        } else {
                                            $fail('A payment document for the selected payment terms already exists (pending approval). Please choose different payment terms or do not mark as payment document.');
                                        }
                                    };
                                },
                            ]),
                    ])
                    ->using(function (array $data): Media {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();

                        $files = $data['document'] ?? null;
                        $files = is_array($files) ? $files : ($files !== null ? [$files] : []);

                        $customProperties = [];
                        if (! empty($data['is_payment_document'])) {
                            $customProperties['is_payment_document'] = true;
                            if (! empty($data['payment_terms'])) {
                                $customProperties['payment_terms'] = $data['payment_terms'];
                            }
                        }

                        $attached = app(AttachUploadedFiles::class)->execute(
                            $request,
                            $files,
                            'completion_reports',
                            Request::COMPLETION_REPORTS_UPLOAD_DIRECTORY,
                            $customProperties,
                        );

                        $name = $data['name'] ?? null;
                        foreach ($attached as $media) {
                            // Preserve the pre-convergence naming: custom name when given,
                            // otherwise the file's basename (extension included).
                            $media->update(['name' => is_string($name) && $name !== '' ? $name : $media->file_name]);
                        }

                        $createdMedia = $attached[0] ?? null;

                        // If no media was created, throw an error to prevent form from closing silently
                        if ($createdMedia === null) {
                            Notification::make()
                                ->title('Upload failed')
                                ->body('No document was uploaded. Please select a file to upload.')
                                ->danger()
                                ->send();

                            throw new \Filament\Support\Exceptions\Halt;
                        }

                        return $createdMedia;
                    })
                    ->after(function (Media $media, array $data): void {
                        Notification::make()
                            ->title('Document uploaded')
                            ->body('Completion report document has been uploaded successfully.')
                            ->success()
                            ->send();
                    })
                    ->successNotificationTitle('Document uploaded successfully'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('download')
                        ->label('Download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->url(fn (Media $record): string => route('documents.download', $record))
                        ->openUrlInNewTab(),
                    Action::make('view')
                        ->label('View')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn (Media $record): string => route('documents.download', $record))
                        ->openUrlInNewTab(),
                    DeleteAction::make()
                        ->label('Delete')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->authorize(fn ($record): bool => true)
                        ->action(function ($record): void {
                            $record->delete();

                            Notification::make()
                                ->title('Document deleted')
                                ->body('Completion report document has been deleted.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->bulkActions([
                //
            ])
            ->emptyStateHeading('No completion reports uploaded')
            ->emptyStateDescription('Upload completion report documents to track project completion.')
            ->emptyStateIcon('heroicon-o-document-check');
    }

    /**
     * Format file size in human-readable format.
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }

    /**
     * Get payment terms options from buyer order.
     *
     * @return array<string, string>
     */
    private function getPaymentTermsOptions(): array
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();
        $buyerOrder = $this->getPrimaryBuyerOrder($request);

        if ($buyerOrder === null || $buyerOrder->buyerQuote === null) {
            return [];
        }

        $quote = $buyerOrder->buyerQuote;
        $paymentTerms = $quote->paymentTerms;

        if ($paymentTerms->isEmpty()) {
            return [];
        }

        $options = [];
        foreach ($paymentTerms as $term) {
            $key = "{$term->due_days}-{$term->percentage}";
            $label = "{$term->due_days} days - {$term->percentage}%";
            $options[$key] = $label;
        }

        return $options;
    }

    /**
     * Get the primary buyer order for payment terms.
     * Prefers confirmed orders, then most recent order.
     */
    private function getPrimaryBuyerOrder(Request $record): ?BuyerOrder
    {
        // Try to get confirmed order first
        $confirmedOrder = $record->buyerOrders()
            ->whereNotIn('status', [OrderStatus::DRAFT, OrderStatus::CANCELLED])
            ->with('buyerQuote.paymentTerms')
            ->orderByDesc('confirmed_at')
            ->first();

        if ($confirmedOrder !== null) {
            return $confirmedOrder;
        }

        // Fall back to most recent order
        return $record->buyerOrders()
            ->with('buyerQuote.paymentTerms')
            ->orderByDesc('created_at')
            ->first();
    }
}
