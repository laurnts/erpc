<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Enums\CentralPurchasingRole;
use App\Models\User;
use App\Services\TeamMemberService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;

/**
 * Reusable select component for selecting Central Purchasing personnel.
 *
 * Supports:
 * - Filtering by Central Purchasing role
 * - Filtering by buyer assignment (optional)
 * - Inline creation with appropriate defaults
 * - Searchable and preloaded options
 */
final class KeyAccountSelect
{
    /**
     * Create a select field for Central Purchasing personnel with a specific role.
     *
     * @param  string  $name  Field name (e.g., 'prepared_by_id')
     * @param  string  $label  Field label
     * @param  string  $relationshipName  Relationship name on the model (e.g., 'preparedBy')
     * @param  CentralPurchasingRole  $role  Required Central Purchasing role
     * @param  int|callable|null  $buyerId  Optional buyer ID or callback to get buyer ID from livewire
     * @return Select
     */
    public static function makeWithRelationship(
        string $name,
        string $label,
        string $relationshipName,
        CentralPurchasingRole $role,
        int|callable|null $buyerId = null
    ): Select {
        /** @var \App\Models\Team $team */
        $team = Filament::getTenant();

        return Select::make($name)
            ->label($label)
            ->relationship(
                $relationshipName,
                'name',
                modifyQueryUsing: function ($query) use ($team, $role, $buyerId) {
                    // Query Users who are team members with the specified Central Purchasing role
                    $query->whereHas('teams', function ($q) use ($team, $role) {
                        $q->where('teams.id', $team->id)
                            ->where('team_user.role', 'central_purchasing')
                            ->where('team_user.central_purchasing_role', $role->value);
                    });

                    // TODO: Filter by buyer assignment when key_account_buyers table is updated to reference users
                    // For now, buyer filtering is disabled since the relationship still references people

                    return $query;
                }
            )
            ->searchable()
            ->preload()
            ->nullable()
            ->createOptionForm([
                \Filament\Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(User::class),
            ])
            ->createOptionUsing(function (array $data) use ($team, $role): int {
                /** @var User $user */
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)), // Temporary password
                ]);

                // Add user to team with Central Purchasing role
                $team->users()->attach($user->id, [
                    'role' => 'central_purchasing',
                    'central_purchasing_role' => $role->value,
                ]);

                return $user->id;
            })
            ->editOptionForm([
                \Filament\Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(User::class, ignorable: fn ($record) => $record),
            ])
            ->editOptionAction(fn ($action) => $action->modalHeading('Edit Central Purchasing Personnel'));
    }
}
