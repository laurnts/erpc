<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerResource\RelationManagers;

use App\Enums\PortalType;
use App\Models\PortalInvitation;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Staff visibility for the window between sending a customer portal
 * invitation and its acceptance — accepted people appear as Portal Users,
 * so this tab lists pending invitations only and disappears when none are
 * outstanding.
 */
final class PortalInvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'portalInvitations';

    protected static ?string $title = 'Pending Invitations';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return parent::canViewForRecord($ownerRecord, $pageClass)
            && self::pendingQuery($ownerRecord)->exists();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = self::pendingQuery($ownerRecord)->count();

        return $count > 0 ? (string) $count : null;
    }

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
                    ->modalDescription('The acceptance link will stop working. This does not affect users who already accepted.'),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('portal', PortalType::Customer)
                ->whereNull('accepted_at')
                ->with('invitedBy'));
    }

    /**
     * @return Builder<PortalInvitation>
     */
    private static function pendingQuery(Model $ownerRecord): Builder
    {
        return PortalInvitation::query()
            ->where('company_id', $ownerRecord->getKey())
            ->where('portal', PortalType::Customer)
            ->whereNull('accepted_at');
    }
}
