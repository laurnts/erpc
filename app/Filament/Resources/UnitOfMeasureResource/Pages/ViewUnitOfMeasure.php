<?php

declare(strict_types=1);

namespace App\Filament\Resources\UnitOfMeasureResource\Pages;

use App\Filament\Resources\UnitOfMeasureResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ViewUnitOfMeasure extends ViewRecord
{
    /** @var class-string<UnitOfMeasureResource> */
    protected static string $resource = UnitOfMeasureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make(),
                DeleteAction::make(),
            ]),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Flex::make([
                    Section::make('Unit Details')
                        ->schema([
                            TextEntry::make('code')
                                ->label('Code')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('label')
                                ->label('Label'),
                            TextEntry::make('sort_order')
                                ->label('Sort Order'),
                            IconEntry::make('is_active')
                                ->label('Active')
                                ->boolean(),
                        ])
                        ->columns(2),
                    Section::make('Status')
                        ->schema([
                            TextEntry::make('creator.name')
                                ->label('Created By'),
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),
                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime(),
                        ])
                        ->grow(false),
                ])->columnSpan('full'),
            ]);
    }
}
