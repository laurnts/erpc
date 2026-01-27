<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Team;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

final class UnitOfMeasureSeeder extends Seeder
{
    /**
     * Default units of measure to seed for each team.
     *
     * @var array<array{code: string, label: string, is_active: bool, sort_order: int}>
     */
    private const array DEFAULT_UNITS = [
        [
            'code' => 'pcs',
            'label' => 'Pieces',
            'is_active' => true,
            'sort_order' => 1,
        ],
        [
            'code' => 'kg',
            'label' => 'Kilograms',
            'is_active' => true,
            'sort_order' => 2,
        ],
        [
            'code' => 'mt',
            'label' => 'Metric Tons',
            'is_active' => true,
            'sort_order' => 3,
        ],
        [
            'code' => 'set',
            'label' => 'Sets',
            'is_active' => true,
            'sort_order' => 4,
        ],
        [
            'code' => 'box',
            'label' => 'Boxes',
            'is_active' => true,
            'sort_order' => 5,
        ],
        [
            'code' => 'roll',
            'label' => 'Rolls',
            'is_active' => true,
            'sort_order' => 6,
        ],
        [
            'code' => 'pair',
            'label' => 'Pairs',
            'is_active' => true,
            'sort_order' => 7,
        ],
        [
            'code' => 'l',
            'label' => 'Liters',
            'is_active' => true,
            'sort_order' => 8,
        ],
        [
            'code' => 'm',
            'label' => 'Meters',
            'is_active' => true,
            'sort_order' => 9,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teams = Team::all();

        foreach ($teams as $team) {
            $this->seedUnitsForTeam($team);
        }
    }

    /**
     * Seed default units of measure for a specific team.
     */
    public function seedUnitsForTeam(Team $team): void
    {
        foreach (self::DEFAULT_UNITS as $unitData) {
            UnitOfMeasure::firstOrCreate(
                [
                    'team_id' => $team->id,
                    'code' => $unitData['code'],
                ],
                [
                    'label' => $unitData['label'],
                    'is_active' => $unitData['is_active'],
                    'sort_order' => $unitData['sort_order'],
                ]
            );
        }
    }

    /**
     * Get the default units array.
     *
     * @return array<array{code: string, label: string, is_active: bool, sort_order: int}>
     */
    public static function getDefaultUnits(): array
    {
        return self::DEFAULT_UNITS;
    }
}
