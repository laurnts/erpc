<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuotationEvaluationResource\Pages;

use App\Filament\Resources\QuotationEvaluationResource;
use App\Filament\Resources\RequestResource;
use App\Models\QuotationEvaluation;
use Barryvdh\DomPDF\Facade\Pdf;
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

final class ViewQuotationEvaluation extends ViewRecord
{
    /** @var class-string<QuotationEvaluationResource> */
    protected static string $resource = QuotationEvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    /** @var QuotationEvaluation $record */
                    $record = $this->record;

                    $pdf = Pdf::loadView('pdf.quotation-evaluation', [
                        'qe' => $record,
                    ])->setPaper('a4', 'landscape');

                    $filename = 'QE-' . str_replace(['/', '\\'], '-', $record->qe_number) . '.pdf';

                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        $filename
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
                    Section::make('QE Information')
                        ->schema([
                            TextEntry::make('qe_number')
                                ->label('QE Number')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('qe_date')
                                ->label('Date')
                                ->date(),
                            TextEntry::make('request.request_number')
                                ->label('Request')
                                ->url(fn (QuotationEvaluation $record): ?string => $record->request_id
                                    ? RequestResource::getUrl('view', ['record' => $record->request_id])
                                    : null)
                                ->color('primary'),
                            TextEntry::make('description')
                                ->label('Description')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ])
                        ->columns(3),
                    Section::make('Status')
                        ->schema([
                            TextEntry::make('creator.name')
                                ->label('Created By'),
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),
                        ])
                        ->grow(false),
                ])->columnSpan('full'),

                Section::make('Item Comparison')
                    ->schema([
                        ViewEntry::make('data')
                            ->label('')
                            ->view('filament.infolists.components.qe-item-comparison'),
                    ])
                    ->columnSpan('full'),

                Section::make('Supplier Information')
                    ->schema([
                        ViewEntry::make('data')
                            ->label('')
                            ->view('filament.infolists.components.qe-supplier-info'),
                    ])
                    ->columnSpan('full')
                    ->collapsible(),

                Section::make('Central Purchasing')
                    ->description('Approval workflow')
                    ->schema([
                        TextEntry::make('preparedBy.name')
                            ->label('Prepared By')
                            ->placeholder('—'),
                        TextEntry::make('dept_head_sales_name')
                            ->label('Dept Head of Sales')
                            ->placeholder('—'),
                        TextEntry::make('deputy_director_name')
                            ->label('Deputy Director')
                            ->placeholder('—'),
                        TextEntry::make('approved_by_name')
                            ->label('Approved By')
                            ->placeholder('—'),
                    ])
                    ->columns(4)
                    ->columnSpan('full'),
            ]);
    }
}
