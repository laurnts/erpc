<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuyerResource\RelationManagers;

use App\Actions\Portal\InvitePortalUser;
use App\Enums\PortalMembershipState;
use App\Enums\PortalType;
use App\Models\CompanyPortalUser;
use App\Models\PortalInvitation;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One list for every portal person of the company across the whole
 * lifecycle: Invited (revoke/resend), Active (deactivate), Deactivated
 * (reactivate). Shared by the buyer and supplier views; the portal type is
 * derived from the page the manager is mounted on.
 */
final class PortalUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'portalMemberships';

    protected static ?string $title = 'Portal Users';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Name'),
                TextColumn::make('display_email')
                    ->label('Email'),
                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->state(fn (CompanyPortalUser $record): PortalMembershipState => $record->state()),
                TextColumn::make('invitedBy.name')
                    ->label('Invited By'),
                TextColumn::make('created_at')
                    ->label('Since')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->authorize(fn (CompanyPortalUser $record): bool => auth()->user()?->can('delete', $record) === true)
                    ->visible(fn (CompanyPortalUser $record): bool => $record->state() === PortalMembershipState::Invited)
                    ->requiresConfirmation()
                    ->modalHeading('Resend invitation email?')
                    ->action(function (CompanyPortalUser $record): void {
                        /** @var Team $team */
                        $team = Filament::getTenant();

                        /** @var User $invitedBy */
                        $invitedBy = auth()->user();

                        app(InvitePortalUser::class)->execute(
                            team: $team,
                            company: $record->company,
                            portal: $record->portal,
                            email: (string) $record->invited_email,
                            name: (string) $record->invited_name,
                            invitedBy: $invitedBy,
                        );
                    }),
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->authorize(fn (CompanyPortalUser $record): bool => auth()->user()?->can('delete', $record) === true)
                    ->visible(fn (CompanyPortalUser $record): bool => $record->state() === PortalMembershipState::Invited)
                    ->requiresConfirmation()
                    ->modalHeading('Revoke this invitation?')
                    ->modalDescription('The acceptance link will stop working and the person disappears from this list.')
                    ->action(function (CompanyPortalUser $record): void {
                        DB::transaction(function () use ($record): void {
                            PortalInvitation::query()
                                ->where('company_id', $record->company_id)
                                ->where('portal', $record->portal)
                                ->where('email', $record->invited_email)
                                ->whereNull('accepted_at')
                                ->delete();

                            $record->delete();
                        });
                    }),
                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->authorize(fn (CompanyPortalUser $record): bool => auth()->user()?->can('update', $record) === true)
                    ->visible(fn (CompanyPortalUser $record): bool => $record->state() === PortalMembershipState::Active)
                    ->requiresConfirmation()
                    ->modalHeading('Deactivate portal access?')
                    ->modalDescription('The user is signed out on their next request and loses access until reactivated.')
                    ->action(fn (CompanyPortalUser $record) => $record->update(['is_active' => false])),
                Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->authorize(fn (CompanyPortalUser $record): bool => auth()->user()?->can('update', $record) === true)
                    ->visible(fn (CompanyPortalUser $record): bool => $record->state() === PortalMembershipState::Deactivated)
                    ->requiresConfirmation()
                    ->modalHeading('Reactivate portal access?')
                    ->action(fn (CompanyPortalUser $record) => $record->update(['is_active' => true])),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('portal', $this->portalType())
                ->with(['user', 'invitedBy', 'company']));
    }

    /**
     * The buyer view manages customer-portal people; the supplier view
     * manages supplier-portal people. Derived from the mounting page so one
     * class serves both resources.
     */
    private function portalType(): PortalType
    {
        return str_contains($this->getPageClass(), 'Supplier')
            ? PortalType::Supplier
            : PortalType::Customer;
    }
}
