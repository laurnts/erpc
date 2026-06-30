<?php

declare(strict_types=1);

namespace App\Filament\Customer\Widgets;

use App\Filament\Customer\Resources\CustomerRequestResource;
use App\Models\Request;
use App\Services\CustomerPortal\CustomerRequestStagePresenter;
use App\Services\CustomerPortal\PortalContext;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

final class PortalRecentRequestsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Recent Requests';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'customer';
    }

    public function table(Table $table): Table
    {
        $presenter = app(CustomerRequestStagePresenter::class);
        $companyId = app(PortalContext::class)->companyId();

        return $table
            ->query(
                Request::query()
                    ->where('buyer_id', $companyId)
                    ->whereNotNull('submitted_at')
                    ->latest('submitted_at')
                    ->limit(10),
            )
            ->columns([
                TextColumn::make('request_number')
                    ->label('Request No.')
                    ->weight('bold'),
                TextColumn::make('title')
                    ->label('Title')
                    ->limit(40),
                TextColumn::make('stage')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (Request $record): string => $presenter->label($record))
                    ->color(fn (Request $record): string => $presenter->color($record->stage)),
                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime(),
            ])
            ->recordUrl(fn (Request $record): string => CustomerRequestResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }
}
