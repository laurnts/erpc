<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Models\KeyAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

final class CentralPurchasingSchema
{
    /**
     * Get the approval workflow schema.
     *
     * @param  int|null  $buyerId  Optional buyer ID to filter key accounts
     * @return array<int, mixed>
     */
    public static function make(?int $buyerId = null): array
    {
        return [
            Section::make('Approval Information')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('prepared_by_id')
                            ->label('Prepared By')
                            ->options(fn () => KeyAccount::selectOptions($buyerId))
                            ->searchable()
                            ->preload(),
                        TextInput::make('dept_head_sales_name')
                            ->label('Dept. Head Sales'),
                        TextInput::make('deputy_director_name')
                            ->label('Deputy Director'),
                        TextInput::make('approved_by_name')
                            ->label('Approved By'),
                    ]),
                ])
                ->collapsible(),
        ];
    }
}
