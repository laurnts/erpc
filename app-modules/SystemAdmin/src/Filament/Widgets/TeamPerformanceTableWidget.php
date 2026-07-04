<?php

declare(strict_types=1);

namespace Relaticle\SystemAdmin\Filament\Widgets;

use App\Enums\CreationSource;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class TeamPerformanceTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Team Performance Analytics';

    protected static ?int $sort = 4;

    /**
     * @return array<string, mixed>
     */
    public function getColumnSpan(): array
    {
        return [
            'default' => 'full',
            'md' => 'full',
            'lg' => 2,
            'xl' => 2,
            '2xl' => 2,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Team Member')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('companies_created')
                    ->label('Companies')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('last_activity')
                    ->label('Last Activity')
                    ->dateTime('M j, Y g:i A')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('companies_created', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->striped()
            ->emptyStateHeading('No Team Activity')
            ->emptyStateDescription('User performance data will appear here once team members start creating companies')
            ->emptyStateIcon('heroicon-o-users');
    }

    /**
     * @return Builder<User>
     */
    protected function getTableQuery(): Builder
    {
        $systemSource = CreationSource::SYSTEM->value;

        return User::query()
            ->addSelect([
                'users.id',
                'users.name',
                'users.created_at',
                DB::raw("(SELECT COUNT(*) FROM companies WHERE companies.creator_id = users.id AND companies.deleted_at IS NULL AND companies.creation_source != '{$systemSource}') as companies_created"),
                DB::raw("COALESCE((SELECT MAX(created_at) FROM companies WHERE creator_id = users.id AND creation_source != '{$systemSource}'), '1970-01-01') as last_activity"),
            ])
            ->whereExists(function (QueryBuilder $query) use ($systemSource): void {
                $query->select(DB::raw(1))
                    ->from('companies')
                    ->whereColumn('companies.creator_id', 'users.id')
                    ->where('companies.creation_source', '!=', $systemSource)
                    ->whereNull('companies.deleted_at');
            });
    }
}
