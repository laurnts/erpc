<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TaxCode;
use App\Models\Team;
use Illuminate\Database\Seeder;

final class TaxCodeSeeder extends Seeder
{
    /**
     * Default tax codes to seed for each team.
     *
     * @var array<array{code: string, name: string, rate: float, is_inclusive_default: bool, is_active: bool, is_default: bool, sort_order: int}>
     */
    private const array DEFAULT_TAX_CODES = [
        [
            'code' => 'PPN11',
            'name' => 'PPN 11%',
            'rate' => 11.00,
            'is_inclusive_default' => false,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ],
        [
            'code' => 'PPN0',
            'name' => 'PPN 0%',
            'rate' => 0.00,
            'is_inclusive_default' => false,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 2,
        ],
        [
            'code' => 'EXEMPT',
            'name' => 'Tax Exempt',
            'rate' => 0.00,
            'is_inclusive_default' => false,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 3,
        ],
        [
            'code' => 'NOTAX',
            'name' => 'No Tax',
            'rate' => 0.00,
            'is_inclusive_default' => false,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 4,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teams = Team::all();

        foreach ($teams as $team) {
            $this->seedTaxCodesForTeam($team);
        }
    }

    /**
     * Seed default tax codes for a specific team.
     */
    public function seedTaxCodesForTeam(Team $team): void
    {
        foreach (self::DEFAULT_TAX_CODES as $taxCodeData) {
            TaxCode::firstOrCreate(
                [
                    'team_id' => $team->id,
                    'code' => $taxCodeData['code'],
                ],
                [
                    'name' => $taxCodeData['name'],
                    'rate' => $taxCodeData['rate'],
                    'is_inclusive_default' => $taxCodeData['is_inclusive_default'],
                    'is_active' => $taxCodeData['is_active'],
                    'is_default' => $taxCodeData['is_default'],
                    'sort_order' => $taxCodeData['sort_order'],
                ]
            );
        }
    }

    /**
     * Get the default tax codes array.
     *
     * @return array<array{code: string, name: string, rate: float, is_inclusive_default: bool, is_active: bool, is_default: bool, sort_order: int}>
     */
    public static function getDefaultTaxCodes(): array
    {
        return self::DEFAULT_TAX_CODES;
    }
}
