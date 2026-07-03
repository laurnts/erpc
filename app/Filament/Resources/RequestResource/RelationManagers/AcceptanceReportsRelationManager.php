<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Enums\RequestStage;
use App\Filament\Resources\AcceptanceReportResource;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Models\Request;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class AcceptanceReportsRelationManager extends RelationManager
{
    use HasRequestStageTab;

    protected static string $relationship = 'acceptanceReports';

    protected static ?string $title = 'Acceptance Reports';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-check';

    protected static function getAssociatedStage(): RequestStage
    {
        // Acceptance reports don't have a specific stage - they replace inbound shipments
        return RequestStage::AWAITING_SHIPMENT;
    }

    protected static function getBaseTabTitle(): string
    {
        return 'Acceptance Reports';
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Request $ownerRecord */
        return $ownerRecord->usesAcceptanceReports();
    }

    public function form(Schema $schema): Schema
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return $schema
            ->components(AcceptanceReportResource::form($schema)->getComponents());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('report_number')
            ->columns([
                TextColumn::make('report_number')
                    ->label('Report Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('reported_at')
                    ->label('Reported Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small)
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var Request $request */
                        $request = $this->getOwnerRecord();
                        $data['request_id'] = $request->id;

                        return $data;
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
