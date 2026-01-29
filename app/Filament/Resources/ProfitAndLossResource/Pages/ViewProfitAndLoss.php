<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProfitAndLossResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Filament\Resources\ProfitAndLossResource;
use App\Filament\Resources\RequestResource;
use App\Models\Membership;
use App\Models\ProfitAndLoss;
use App\Services\Erp\PdfGenerationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ViewProfitAndLoss extends ViewRecord
{
    /** @var class-string<ProfitAndLossResource> */
    protected static string $resource = ProfitAndLossResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    /** @var ProfitAndLoss $record */
                    $record = $this->record;

                    $service = app(PdfGenerationService::class);
                    $pdf = $service->generateProfitAndLossPdf($record);
                    $filename = $service->getProfitAndLossFilename($record);

                    $content = $pdf->output();

                    return response()->streamDownload(
                        callback: static function () use ($content): void {
                            echo $content;
                        },
                        name: $filename,
                        headers: [
                            'Content-Type' => 'application/pdf',
                        ],
                    );
                }),
            ActionGroup::make([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Flex::make([
                    Section::make('PNL Information')
                        ->schema([
                            TextEntry::make('pnl_number')
                                ->label('PNL Number')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('pnl_date')
                                ->label('Date')
                                ->date(),
                            TextEntry::make('request.request_number')
                                ->label('Request')
                                ->url(fn (ProfitAndLoss $record): ?string => $record->request_id
                                    ? RequestResource::getUrl('view', ['record' => $record->request_id])
                                    : null)
                                ->color('primary'),
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge(),
                            TextEntry::make('description')
                                ->label('Description')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ])
                        ->columns(4),
                    Section::make('Info')
                        ->schema([
                            TextEntry::make('creator.name')
                                ->label('Created By'),
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),
                        ])
                        ->grow(false),
                ])->columnSpan('full'),

                Section::make('Selected Items by Supplier')
                    ->schema([
                        ViewEntry::make('selected_items')
                            ->label('')
                            ->view('filament.infolists.components.pnl-selected-items'),
                    ])
                    ->columnSpan('full'),

                Section::make('Central Purchasing')
                    ->description('Approval workflow')
                    ->schema([
                        TextEntry::make('preparedBy.name')
                            ->label('Prepared By')
                            ->placeholder('—')
                            ->url(function (\App\Models\ProfitAndLoss $record): ?string {
                                if (! $record->prepared_by_id) {
                                    return null;
                                }
                                $membership = Membership::where('team_id', $record->team_id)
                                    ->where('user_id', $record->prepared_by_id)
                                    ->first();
                                return $membership ? MemberResource::getUrl('view', ['record' => $membership]) : null;
                            })
                            ->color('primary'),
                        TextEntry::make('deptHeadSales.name')
                            ->label('Dept Head of Sales')
                            ->placeholder('—')
                            ->url(function (\App\Models\ProfitAndLoss $record): ?string {
                                if (! $record->dept_head_sales_id) {
                                    return null;
                                }
                                $membership = Membership::where('team_id', $record->team_id)
                                    ->where('user_id', $record->dept_head_sales_id)
                                    ->first();
                                return $membership ? MemberResource::getUrl('view', ['record' => $membership]) : null;
                            })
                            ->color('primary'),
                        TextEntry::make('deputyDirector.name')
                            ->label('Deputy Director')
                            ->placeholder('—')
                            ->url(function (\App\Models\ProfitAndLoss $record): ?string {
                                if (! $record->deputy_director_id) {
                                    return null;
                                }
                                $membership = Membership::where('team_id', $record->team_id)
                                    ->where('user_id', $record->deputy_director_id)
                                    ->first();
                                return $membership ? MemberResource::getUrl('view', ['record' => $membership]) : null;
                            })
                            ->color('primary'),
                        TextEntry::make('approvedBy.name')
                            ->label('Approved By')
                            ->placeholder('—')
                            ->url(function (\App\Models\ProfitAndLoss $record): ?string {
                                if (! $record->approved_by_id) {
                                    return null;
                                }
                                $membership = Membership::where('team_id', $record->team_id)
                                    ->where('user_id', $record->approved_by_id)
                                    ->first();
                                return $membership ? MemberResource::getUrl('view', ['record' => $membership]) : null;
                            })
                            ->color('primary'),
                    ])
                    ->columns(4)
                    ->columnSpan('full'),
            ]);
    }
}
