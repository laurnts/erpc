<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Enums\CentralPurchasingRole;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

/**
 * Schema component for approval workflow personnel fields.
 * Uses KeyAccountSelect for consistent key account selection.
 */
final class ApprovalPersonnelSchema
{
    /**
     * Get the approval workflow schema.
     *
     * @param  int|null|callable  $buyerId  Optional buyer ID or callback to get buyer ID from livewire
     * @param  string  $sectionTitle  Section title (default: 'Approval Information')
     * @param  int  $columns  Number of columns for the grid (default: 2)
     * @return array<int, mixed>
     */
    public static function make(int|callable|null $buyerId = null, string $sectionTitle = 'Approval Information', int $columns = 2): array
    {
        // Resolve buyer ID if it's a callback (will be resolved per field)
        $isCallback = is_callable($buyerId);

        return [
            Section::make($sectionTitle)
                ->description('Approval workflow personnel')
                ->schema([
                    Grid::make($columns)->schema([
                        self::makeKeyAccountSelect(
                            'prepared_by_id',
                            'Prepared By',
                            'preparedBy',
                            CentralPurchasingRole::KEY_ACCOUNT,
                            $buyerId,
                            $isCallback
                        ),
                        self::makeKeyAccountSelect(
                            'dept_head_sales_id',
                            'Acknowledged By - Dept Head of Sales',
                            'deptHeadSales',
                            CentralPurchasingRole::DEPT_HEAD_SALES,
                            $buyerId,
                            $isCallback
                        ),
                        self::makeKeyAccountSelect(
                            'deputy_director_id',
                            'Acknowledged By - Deputy Director',
                            'deputyDirector',
                            CentralPurchasingRole::DEPUTY_DIRECTOR,
                            $buyerId,
                            $isCallback
                        ),
                        self::makeKeyAccountSelect(
                            'approved_by_id',
                            'Approved By',
                            'approvedBy',
                            CentralPurchasingRole::DIRECTOR,
                            $buyerId,
                            $isCallback
                        ),
                    ]),
                ])
                ->collapsible(),
        ];
    }

    /**
     * Create a KeyAccount select field with optional buyer filtering.
     */
    private static function makeKeyAccountSelect(
        string $name,
        string $label,
        string $relationshipName,
        CentralPurchasingRole $role,
        int|callable|null $buyerId,
        bool $isCallback
    ): \Filament\Forms\Components\Select {
        // Pass the buyerId (callable or int) directly to KeyAccountSelect
        // It will handle the callable resolution internally
        return KeyAccountSelect::makeWithRelationship(
            $name,
            $label,
            $relationshipName,
            $role,
            $buyerId
        );
    }
}
