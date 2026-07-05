<?php

declare(strict_types=1);

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\ArticleResource\RelationManagers\SuppliersRelationManager;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ViewArticle extends ViewRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make(),
                RestoreAction::make(),
                DeleteAction::make(),
            ]),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make('Article Details')
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Code')
                                    ->weight('bold')
                                    ->copyable(),
                                TextEntry::make('name')
                                    ->label('Name'),
                                TextEntry::make('sku')
                                    ->label('SKU')
                                    ->placeholder('—'),
                                TextEntry::make('unitOfMeasure.label')
                                    ->label('Unit of Measure')
                                    ->placeholder('—'),
                                TextEntry::make('defaultTaxCode.name')
                                    ->label('Default Tax Code')
                                    ->placeholder('—'),
                                TextEntry::make('description')
                                    ->label('Description')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpan(2),
                        Section::make('Status')
                            ->schema([
                                IconEntry::make('is_active')
                                    ->label('Active')
                                    ->boolean(),
                                TextEntry::make('creator.name')
                                    ->label('Created By'),
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columnSpan('full'),
                Section::make('Images')
                    ->schema([
                        SpatieMediaLibraryImageEntry::make('product_images')
                            ->label('')
                            ->collection('product_images')
                            ->conversion('thumb')
                            ->placeholder('No images uploaded.'),
                    ])
                    ->collapsible()
                    ->columnSpan('full'),
            ]);
    }

    public function getRelationManagers(): array
    {
        return [
            SuppliersRelationManager::class,
        ];
    }
}
