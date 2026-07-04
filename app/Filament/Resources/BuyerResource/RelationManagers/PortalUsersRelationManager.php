<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerResource\RelationManagers;

use App\Enums\PortalType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PortalUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'portalMemberships';

    protected static ?string $title = 'Portal Users';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime(),
            ])
            ->modifyQueryUsing(fn ($query) => $query
                ->where('portal', PortalType::Customer)
                ->with(['user', 'invitedBy']));
    }
}
