<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProfitAndLossResource\Pages;

use App\Enums\CentralPurchasingRole;
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

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // Ensure relationships are loaded
        $this->record->load(['preparedBy', 'deptHeadSales', 'deputyDirector', 'approvedBy']);
    }

    protected function mutateInfolistData(array $data): array
    {
        // Ensure relationships are loaded before infolist renders
        $this->record->loadMissing(['preparedBy', 'deptHeadSales', 'deputyDirector', 'approvedBy']);
        
        return $data;
    }

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
                EditAction::make()
                    ->slideOver()
                    ->beforeFormFilled(function ($record) {
                        // Ensure request relationship is loaded before form is built
                        $record->load('request');
                    }),
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
                        TextEntry::make('prepared_by_id')
                            ->label('Prepared By')
                            ->getStateUsing(function (\App\Models\ProfitAndLoss $record): ?string {
                                $record->loadMissing('preparedBy');
                                if (! $record->preparedBy) {
                                    return null;
                                }
                                
                                // Verify user has correct role
                                $membership = Membership::where('team_id', $record->team_id)
                                    ->where('user_id', $record->prepared_by_id)
                                    ->where('role', 'central_purchasing')
                                    ->where('central_purchasing_role', CentralPurchasingRole::KEY_ACCOUNT->value)
                                    ->first();
                                
                                return $membership ? $record->preparedBy->name : null;
                            })
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
                        TextEntry::make('dept_head_sales_id')
                            ->label('Dept Head of Sales')
                            ->getStateUsing(function (\App\Models\ProfitAndLoss $record): ?string {
                                $record->loadMissing('deptHeadSales');
                                if (! $record->deptHeadSales) {
                                    return null;
                                }
                                
                                // Verify user has correct role
                                $membership = Membership::where('team_id', $record->team_id)
                                    ->where('user_id', $record->dept_head_sales_id)
                                    ->where('role', 'central_purchasing')
                                    ->where('central_purchasing_role', CentralPurchasingRole::DEPT_HEAD_SALES->value)
                                    ->first();
                                
                                return $membership ? $record->deptHeadSales->name : null;
                            })
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
                        TextEntry::make('deputy_director_id')
                            ->label('Deputy Director')
                            ->getStateUsing(function (\App\Models\ProfitAndLoss $record): ?string {
                                $record->loadMissing('deputyDirector');
                                if (! $record->deputyDirector) {
                                    return null;
                                }
                                
                                // Verify user has correct role
                                $membership = Membership::where('team_id', $record->team_id)
                                    ->where('user_id', $record->deputy_director_id)
                                    ->where('role', 'central_purchasing')
                                    ->where('central_purchasing_role', CentralPurchasingRole::DEPUTY_DIRECTOR->value)
                                    ->first();
                                
                                return $membership ? $record->deputyDirector->name : null;
                            })
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
                        TextEntry::make('approved_by_id')
                            ->label('Approved By')
                            ->getStateUsing(function (\App\Models\ProfitAndLoss $record): ?string {
                                $record->loadMissing('approvedBy');
                                if (! $record->approvedBy) {
                                    return null;
                                }
                                
                                // Verify user has correct role
                                $membership = Membership::where('team_id', $record->team_id)
                                    ->where('user_id', $record->approved_by_id)
                                    ->where('role', 'central_purchasing')
                                    ->where('central_purchasing_role', CentralPurchasingRole::DIRECTOR->value)
                                    ->first();
                                
                                return $membership ? $record->approvedBy->name : null;
                            })
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
