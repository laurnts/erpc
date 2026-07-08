<?php

declare(strict_types=1);

namespace App\Filament\Buyer\Resources\BuyerRequestResource\RelationManagers;

use App\Enums\BuyerQuoteStatus;
use App\Filament\Actions\DownloadPdfAction;
use App\Models\BuyerQuote;
use App\Services\BuyerPortal\BuyerQuoteStatusPresenter;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
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

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $statusPresenter = app(BuyerQuoteStatusPresenter::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('status', '!=', BuyerQuoteStatus::DRAFT)
                ->with('currency')
                ->orderByDesc('created_at'))
            ->heading('')
            ->description(fn (): ?HtmlString => $this->getQuotesFooter())
            ->columns([
                TextColumn::make('quote_number')
                    ->label('Quote No.')
                    ->weight(fn (BuyerQuote $record): string => $record->status === BuyerQuoteStatus::SENT ? 'bold' : 'medium')
                    ->extraAttributes(fn (BuyerQuote $record): array => $this->rowAttributes($record)),
                TextColumn::make('version')
                    ->label('Version')
                    ->alignCenter()
                    ->extraAttributes(fn (BuyerQuote $record): array => $this->rowAttributes($record)),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (BuyerQuote $record): string => sprintf(
                        '%s %s',
                        $record->currency?->code ?? '',
                        number_format((float) $record->total, 2),
                    ))
                    ->extraAttributes(fn (BuyerQuote $record): array => $this->rowAttributes($record)),
                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date()
                    ->placeholder('-')
                    ->extraAttributes(fn (BuyerQuote $record): array => $this->rowAttributes($record)),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (BuyerQuoteStatus $state): string => $statusPresenter->label($state))
                    ->color(fn (BuyerQuoteStatus $state): string => $statusPresenter->color($state))
                    ->icon(fn (BuyerQuoteStatus $state): ?string => $statusPresenter->icon($state))
                    ->extraAttributes(fn (BuyerQuote $record): array => $this->rowAttributes($record)),
            ])
            ->recordActions([
                DownloadPdfAction::make()
                    ->label('PDF')
                    ->authorize(fn (BuyerQuote $record): bool => $this->portalUser()?->can('view', $record) ?? false),
            ])
            ->recordActionsColumnLabel('Actions');
    }

    /**
     * @return array<string, string>
     */
    private function rowAttributes(BuyerQuote $record): array
    {
        if ($record->status === BuyerQuoteStatus::SUPERSEDED) {
            return ['class' => 'opacity-50'];
        }

        return [];
    }

    private function getQuotesFooter(): ?HtmlString
    {
        $count = $this->getOwnerRecord()->buyerQuotes()
            ->where('status', '!=', BuyerQuoteStatus::DRAFT)
            ->count();

        if ($count === 0) {
            return null;
        }

        $hasPending = $this->getOwnerRecord()->buyerQuotes()
            ->where('status', BuyerQuoteStatus::SENT)
            ->exists();

        $suffix = $hasPending
            ? 'only the current version needs your decision, above.'
            : 'historical versions are shown for reference.';

        return new HtmlString(sprintf(
            '<span class="text-xs text-gray-500 dark:text-gray-400">Showing %d %s · %s</span>',
            $count,
            \Illuminate\Support\Str::plural('result', $count),
            $suffix,
        ));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    private function portalUser(): ?\App\Models\User
    {
        $user = Filament::auth()->user();

        return $user instanceof \App\Models\User ? $user : null;
    }
}
