<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Enums\CentralPurchasingRole;
use App\Filament\Resources\PeopleResource;
use App\Models\People;
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
        return Select::make($name)
            ->label($label)
            ->relationship(
                $relationshipName,
                'name',
                modifyQueryUsing: function ($query, $livewire) use ($role, $buyerId) {
                    $query->where('is_central_purchasing', true)
                        ->where('central_purchasing_role', $role->value);

                    // Resolve buyer ID if it's a callable
                    $resolvedBuyerId = is_callable($buyerId) ? $buyerId($livewire) : $buyerId;

                    // Filter to only show key accounts assigned to handle the specified buyer (only for KEY_ACCOUNT role)
                    if ($resolvedBuyerId && $role === CentralPurchasingRole::KEY_ACCOUNT) {
                        $query->whereHas('buyers', function ($q) use ($resolvedBuyerId): void {
                            $q->where('companies.id', $resolvedBuyerId);
                        });
                    }

                    return $query;
                }
            )
            ->searchable()
            ->preload()
            ->nullable()
            ->createOptionForm(PeopleResource::getFormSchema())
            ->createOptionUsing(function (array $data) use ($role): int {
                /** @var \App\Models\Team $team */
                $team = Filament::getTenant();

                /** @var People $person */
                $person = People::create([
                    'name' => $data['name'],
                    'is_central_purchasing' => true,
                    'central_purchasing_role' => $role,
                    'team_id' => $team->id,
                    'creator_id' => auth()->id(),
                ]);

                return $person->id;
            })
            ->editOptionForm(PeopleResource::getFormSchema())
            ->editOptionAction(fn ($action) => $action->modalHeading('Edit Central Purchasing Personnel'));
    }
}
