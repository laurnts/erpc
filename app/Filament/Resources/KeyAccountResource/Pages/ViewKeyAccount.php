<?php

declare(strict_types=1);

namespace App\Filament\Resources\KeyAccountResource\Pages;

use App\Filament\Resources\KeyAccountResource;
use App\Filament\Resources\KeyAccountResource\RelationManagers\BuyersRelationManager;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ViewKeyAccount extends ViewRecord
{
    protected static string $resource = KeyAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Flex::make([
                    Section::make('Key Account Details')
                        ->schema([
                            TextEntry::make('name')
                                ->label('Name')
                                ->weight('bold'),
                            TextEntry::make('email')
                                ->label('Email')
                                ->copyable()
                                ->placeholder('—'),
                            TextEntry::make('phone')
                                ->label('Phone')
                                ->copyable()
                                ->placeholder('—'),
                        ])
                        ->columns(2),
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
                        ->grow(false),
                ])->columnSpan('full'),
            ]);
    }

    public function getRelationManagers(): array
    {
        return [
            BuyersRelationManager::class,
        ];
    }
}
