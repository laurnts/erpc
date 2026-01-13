<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Models\Article;
use App\Models\Request;
use App\Models\RequestItem;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-queue-list';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('description')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->helperText('Enter a vague description. You can match it to an article later.'),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->minValue(0.0001)
                    ->default(1),
                TextInput::make('unit')
                    ->maxLength(50)
                    ->default('pcs'),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();
        $canEdit = $request->canEditItems();

        $matchedCount = $request->items()->where('is_matched', true)->count();
        $totalCount = $request->items()->count();
        $allMatched = $matchedCount === $totalCount;

        return $table
            ->recordTitleAttribute('description')
            ->description($allMatched || $totalCount === 0
                ? null
                : "Warning: {$matchedCount} of {$totalCount} items matched. Match all items before requesting supplier quotes.")
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width(50),
                IconColumn::make('is_matched')
                    ->label('Matched')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->width(80),
                TextColumn::make('description')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (RequestItem $record): ?string => $record->description),
                TextColumn::make('article.code')
                    ->label('Article')
                    ->placeholder('Not matched')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('unit'),
            ])
            ->filters([
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small)
                    ->visible($canEdit),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible($canEdit),
                Action::make('match')
                    ->label('Match Article')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->modalHeading('Match to Article')
                    ->visible(fn (RequestItem $record): bool => $canEdit && ! $record->is_matched)
                    ->form([
                        Placeholder::make('buyer_request_context')
                            ->label('Buyer Requested')
                            ->content(fn (RequestItem $record): string => "\"{$record->description}\"\nQty: {$record->quantity} {$record->unit}"),
                        Select::make('article_id')
                            ->label('Select Article')
                            ->options(
                                Article::query()
                                    ->where('team_id', $request->team_id)
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Article $article): array => [
                                        $article->getKey() => "[{$article->code}] {$article->name}",
                                    ])
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Search existing articles or create a new one first'),
                    ])
                    ->action(function (RequestItem $record, array $data): void {
                        $article = Article::findOrFail($data['article_id']);
                        $record->matchToArticle($article);

                        Notification::make()
                            ->title('Item matched to article')
                            ->success()
                            ->send();
                    }),
                Action::make('unmatch')
                    ->label('Unmatch')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (RequestItem $record): bool => $canEdit && $record->is_matched)
                    ->requiresConfirmation()
                    ->action(function (RequestItem $record): void {
                        $record->unmatch();

                        Notification::make()
                            ->title('Item unmatched')
                            ->warning()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible($canEdit),
                ]),
            ]);
    }
}
