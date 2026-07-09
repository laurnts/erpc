<?php

declare(strict_types=1);

namespace App\Filament\Buyer\Resources\BuyerRequestResource\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Filament\Actions\DownloadPdfAction;
use App\Models\BuyerInvoice;
use App\Models\Request;
use App\Services\BuyerPortal\BuyerInvoiceStatusPresenter;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'buyerInvoices';

    protected static ?string $title = 'Invoices';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-currency-dollar';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Request $ownerRecord */
        return $ownerRecord->buyerInvoices()
            ->where('status', '!=', InvoiceStatus::DRAFT)
            ->exists();
    }

    public function table(Table $table): Table
    {
        $invoiceStatusPresenter = app(BuyerInvoiceStatusPresenter::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('status', '!=', InvoiceStatus::DRAFT)
                ->with('currency')
                ->orderByDesc('issued_at'))
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice No.')
                    ->weight('bold'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (BuyerInvoice $record): string => sprintf(
                        '%s %s',
                        $record->currency?->code ?? '',
                        number_format((float) $record->total, 2),
                    )),
                TextColumn::make('amount_paid')
                    ->label('Paid')
                    ->formatStateUsing(fn (BuyerInvoice $record): string => sprintf(
                        '%s %s',
                        $record->currency?->code ?? '',
                        number_format((float) $record->amount_paid, 2),
                    )),
                TextColumn::make('issued_at')
                    ->label('Issued')
                    ->date()
                    ->placeholder('-'),
                TextColumn::make('due_at')
                    ->label('Due')
                    ->date()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (InvoiceStatus $state): string => $invoiceStatusPresenter->label($state))
                    ->color(fn (InvoiceStatus $state): string => $invoiceStatusPresenter->color($state))
                    ->icon(fn (InvoiceStatus $state): ?string => $invoiceStatusPresenter->icon($state)),
            ])
            ->recordActions([
                DownloadPdfAction::make()
                    ->label('PDF')
                    ->authorize(fn (BuyerInvoice $record): bool => ($user = Filament::auth()->user()) !== null && $user->can('view', $record)),
            ])
            ->emptyStateHeading('No invoices yet')
            ->emptyStateDescription('Invoices will appear here once they are issued.');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
