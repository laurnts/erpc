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

        // Build base query for relationship (without buyer filtering for validation)
        $baseQuery = User::query()
            ->whereHas('teams', function ($q) use ($team, $role) {
                $q->where('teams.id', $team->id)
                    ->where('team_user.role', 'central_purchasing')
                    ->where('team_user.central_purchasing_role', $role->value);
            });

        return Select::make($name)
            ->label($label)
            ->options(function ($get, $livewire) use ($team, $role, $buyerId, $name, $relationshipName) {
                // Build query from scratch to avoid Filament's relationship query constraints
                $currentValue = null;
                if ($livewire && isset($livewire->record) && $livewire->record) {
                    $currentValue = $livewire->record->getAttribute($name);
                } elseif ($livewire && isset($livewire->data[$name])) {
                    $currentValue = $livewire->data[$name];
                }
                
                $query = User::query();
                
                // Base query: ONLY users who are team members with the specified Central Purchasing role
                $query->whereHas('teams', function ($q) use ($team, $role) {
                    $q->where('teams.id', $team->id)
                        ->where('team_user.role', 'central_purchasing')
                        ->where('team_user.central_purchasing_role', $role->value);
                });
                
                // Filter by buyer assignment ONLY for Key Account role
                if ($buyerId !== null && $role === CentralPurchasingRole::KEY_ACCOUNT) {
                    $resolvedBuyerId = null;
                    
                    if ($livewire && isset($livewire->record) && $livewire->record) {
                        if (!$livewire->record->relationLoaded('request')) {
                            $livewire->record->load('request');
                        }
                        
                        if ($livewire->record->request && $livewire->record->request->buyer_id) {
                            $resolvedBuyerId = $livewire->record->request->buyer_id;
                        } elseif ($livewire->record->request_id) {
                            $request = \App\Models\Request::find($livewire->record->request_id);
                            $resolvedBuyerId = $request?->buyer_id;
                        } elseif (is_callable($buyerId)) {
                            try {
                                $resolvedBuyerId = $buyerId($livewire);
                            } catch (\Exception $e) {
                                $resolvedBuyerId = null;
                            }
                        }
                    } elseif (is_callable($buyerId)) {
                        try {
                            $resolvedBuyerId = $buyerId($livewire);
                        } catch (\Exception $e) {
                            $resolvedBuyerId = null;
                        }
                    } else {
                        $resolvedBuyerId = $buyerId;
                    }
                    
                    if ($resolvedBuyerId !== null) {
                        // Only show key accounts assigned to this buyer
                        $query->whereExists(function ($subQuery) use ($resolvedBuyerId) {
                            $subQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                                ->from('key_account_buyers')
                                ->whereColumn('key_account_buyers.key_account_id', 'users.id')
                                ->where('key_account_buyers.buyer_id', $resolvedBuyerId);
                        });
                    }
                }
                
                return $query->pluck('name', 'id')->toArray();
            })
            // REMOVED ->relationship() because Filament ignores ->options() when both are present
            // When prepared_by_id is NULL, Filament doesn't call modifyQueryUsing, so no options are loaded
            // Using only ->options() ensures it's always called
            // Filament will save directly to the model attribute (e.g., prepared_by_id)
            // The BelongsTo relationship on the model will handle the relationship binding automatically
            ->rules([
                \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) use ($team, $role) {
                    return $query->whereExists(function ($subQuery) use ($team, $role) {
                        $subQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                            ->from('team_user')
                            ->whereColumn('team_user.user_id', 'users.id')
                            ->where('team_user.team_id', $team->id)
                            ->where('team_user.role', 'central_purchasing')
                            ->where('team_user.central_purchasing_role', $role->value);
                    });
                }),
            ])
            ->selectablePlaceholder(false)
            ->nullable()
            ->searchable()
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
            });
    }
}
