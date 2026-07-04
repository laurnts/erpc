<?php

declare(strict_types=1);

namespace App\Filament\Customer\Resources\CustomerRequestResource\RelationManagers;

use App\Actions\Media\AttachUploadedFiles;
use App\Enums\BuyerQuoteStatus;
use App\Models\BuyerQuote;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

final class BuyerQuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'buyerQuotes';

    protected static ?string $title = 'Quotes';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-text';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('status', '!=', BuyerQuoteStatus::DRAFT)
                ->with('currency')
                ->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('quote_number')
                    ->label('Quote No.')
                    ->weight('bold'),
                TextColumn::make('version')
                    ->label('Version')
                    ->alignCenter(),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (BuyerQuote $record): string => sprintf(
                        '%s %s',
                        $record->currency?->code ?? '',
                        number_format((float) $record->total, 2),
                    )),
                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->recordActions([
                Action::make('accept')
                    ->label('Accept')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (BuyerQuote $record): bool => $record->status === BuyerQuoteStatus::SENT)
                    ->authorize(fn (BuyerQuote $record): bool => auth()->user()?->can('respond', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Accept this quote?')
                    ->action(function (BuyerQuote $record): void {
                        $record->markAsAccepted();

                        Notification::make()
                            ->title('Quote accepted')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (BuyerQuote $record): bool => $record->status === BuyerQuoteStatus::SENT)
                    ->authorize(fn (BuyerQuote $record): bool => auth()->user()?->can('respond', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Reject this quote?')
                    ->action(function (BuyerQuote $record): void {
                        $record->markAsRejected();

                        Notification::make()
                            ->title('Quote rejected')
                            ->warning()
                            ->send();
                    }),
                Action::make('uploadPo')
                    ->label(fn (BuyerQuote $record): string => $record->getMedia('buyer_po')->isNotEmpty() ? 'View PO' : 'Upload PO')
                    ->icon(fn (BuyerQuote $record): string => $record->getMedia('buyer_po')->isNotEmpty() ? 'heroicon-o-eye' : 'heroicon-o-document-arrow-up')
                    ->color('primary')
                    ->visible(fn (BuyerQuote $record): bool => $record->status === BuyerQuoteStatus::SENT)
                    ->authorize(fn (BuyerQuote $record): bool => auth()->user()?->can('uploadPo', $record) ?? false)
                    ->schema(fn (BuyerQuote $record): array => $this->getPoUploadSchema($record))
                    ->modalSubmitAction(fn (BuyerQuote $record): bool => $record->getMedia('buyer_po')->isEmpty())
                    ->modalCancelActionLabel(fn (BuyerQuote $record): string => $record->getMedia('buyer_po')->isNotEmpty() ? 'Close' : 'Cancel')
                    ->action(function (BuyerQuote $record, array $data): void {
                        if ($record->getMedia('buyer_po')->isNotEmpty()) {
                            return;
                        }

                        app(AttachUploadedFiles::class)->execute($record, $data['buyer_po_files'] ?? [], 'buyer_po', BuyerQuote::PO_FILES_UPLOAD_DIRECTORY);

                        $record->refresh();

                        if ($record->status === BuyerQuoteStatus::SENT && $record->getMedia('buyer_po')->isNotEmpty()) {
                            $record->markAsAccepted();
                        }

                        Notification::make()
                            ->title('PO uploaded successfully')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    private function getPoUploadSchema(BuyerQuote $record): array
    {
        $media = $record->getMedia('buyer_po');

        if ($media->isNotEmpty()) {
            $list = $media->map(fn ($item): string => '• '.$item->file_name)->implode('<br>');

            return [
                Placeholder::make('uploaded_files')
                    ->label('PO File')
                    ->content(new HtmlString($list)),
            ];
        }

        return [
            Section::make('Upload Purchase Order')
                ->schema([
                    FileUpload::make('buyer_po_files')
                        ->label('PO File')
                        ->helperText('PDF, Excel, Word, or images (max 2MB per file)')
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
                        ->directory(BuyerQuote::PO_FILES_UPLOAD_DIRECTORY)
                        ->visibility('private')
                        ->multiple()
                        ->maxFiles(10)
                        ->maxSize(2048)
                        ->required(),
                ]),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
