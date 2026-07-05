<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerResource\RelationManagers;

use App\Enums\PortalType;
use App\Models\PortalInvitation;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Staff visibility for customer portal invitations, including the window
 * between sending and acceptance — memberships only appear in Portal Users
 * once the invitee has accepted, so pending invitations would otherwise be
 * invisible.
 */
final class PortalInvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'portalInvitations';

    protected static ?string $title = 'Portal Invitations';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('accepted_at')
                    ->label('Status')
                    ->badge()
                    ->state(fn (PortalInvitation $record): string => $record->accepted_at === null ? 'Pending' : 'Accepted')
                    ->color(fn (PortalInvitation $record): string => $record->accepted_at === null ? 'warning' : 'success'),
                TextColumn::make('invitedBy.name')
                    ->label('Invited By'),
                TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime(),
            ])
            ->recordActions([
                DeleteAction::make('revoke')
                    ->label('Revoke')
                    ->modalHeading('Revoke this invitation?')
                    ->modalDescription('The acceptance link will stop working. This does not affect users who already accepted.')
                    ->visible(fn (PortalInvitation $record): bool => $record->accepted_at === null),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query
                ->where('portal', PortalType::Customer)
                ->with('invitedBy'));
    }
}
