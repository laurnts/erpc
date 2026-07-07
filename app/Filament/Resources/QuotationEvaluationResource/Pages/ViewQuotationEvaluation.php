<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuotationEvaluationResource\Pages;

use App\Actions\Media\AttachUploadedFiles;
use App\Actions\SupplierPortal\AnnounceRfqOutcomes;
use App\Enums\CentralPurchasingRole;
use App\Enums\QEStatus;
use App\Enums\SupplierQuoteStatus;
use App\Filament\Resources\MemberResource;
use App\Filament\Resources\QuotationEvaluationResource;
use App\Filament\Resources\RequestResource;
use App\Models\Membership;
use App\Models\QuotationEvaluation;
use App\Services\Erp\PdfGenerationService;
use App\Support\DocumentUpload;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

final class ViewQuotationEvaluation extends ViewRecord
{
    /** @var class-string<QuotationEvaluationResource> */
    protected static string $resource = QuotationEvaluationResource::class;

    public function mount(int|string $record): void
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
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Quotation Evaluation?')
                ->modalDescription(fn (QuotationEvaluation $record): string => 'Are you sure you want to approve this Quotation Evaluation?')
                ->action(function (QuotationEvaluation $record): void {
                    /** @var \App\Models\User $user */
                    $user = auth()->user();

                    try {
                        $record->approve($user);

                        Notification::make()
                            ->title('Quotation Evaluation approved')
                            ->body('Your approval has been recorded.')
                            ->success()
                            ->send();

                        if ($record->status === QEStatus::APPROVED && $this->canAnnounceRfqOutcomes($record)) {
                            Notification::make()
                                ->title('Announce RFQ outcomes?')
                                ->body('The evaluation is fully approved. You can now announce won/lost outcomes to the suppliers from this page.')
                                ->info()
                                ->send();
                        }

                        // Refresh the page to show updated status
                        $this->redirect(QuotationEvaluationResource::getUrl('view', ['record' => $record]));
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Cannot approve')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (QuotationEvaluation $record): bool => $record->canBeApprovedBy(auth()->user())),
            Action::make('announceOutcomes')
                ->label('Announce RFQ Outcomes')
                ->icon('heroicon-o-megaphone')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Announce RFQ outcomes to suppliers?')
                ->modalDescription('Losing quotes will be marked as rejected, suppliers will be notified of their result, and supplier selections will be locked for this request. This cannot be undone.')
                ->visible(fn (QuotationEvaluation $record): bool => $this->canAnnounceRfqOutcomes($record))
                ->action(function (QuotationEvaluation $record): void {
                    $request = $record->request;
                    $result = $request === null ? null : app(AnnounceRfqOutcomes::class)->execute($request);

                    if ($result === null) {
                        Notification::make()
                            ->title('Nothing to announce')
                            ->body('Outcomes were already announced, or there are no evaluated quotes for this request.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Outcomes announced')
                        ->body(sprintf(
                            '%d winning and %d losing quote(s) finalized. Suppliers have been notified and selections are now locked.',
                            $result['winners'],
                            $result['losers'],
                        ))
                        ->success()
                        ->send();
                }),
            ActionGroup::make([
                EditAction::make()
                    ->url(null)
                    ->slideOver()
                    ->beforeFormFilled(function ($record) {
                        // Ensure request relationship is loaded before form is built
                        $record->load('request');
                    }),
                Action::make('downloadPdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        /** @var QuotationEvaluation $record */
                        $record = $this->record;

                        $service = app(PdfGenerationService::class);
                        $pdf = $service->generateQuotationEvaluationPdf($record);
                        $filename = $service->getQuotationEvaluationFilename($record);
                        $content = $pdf->output();

                        return response()->streamDownload(
                            callback: static function () use ($content): void {
                                echo $content;
                            },
                            name: $filename,
                            headers: ['Content-Type' => 'application/pdf'],
                        );
                    }),
                Action::make('uploadDocument')
                    ->label('Upload Document')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        FileUpload::make('document')
                            ->label('Document')
                            ->helperText(DocumentUpload::helperText(10240))
                            ->required()
                            ->disk('local')
                            ->directory(QuotationEvaluation::DOCUMENTS_UPLOAD_DIRECTORY)
                            ->visibility('private')
                            ->acceptedFileTypes(DocumentUpload::ACCEPTED_MIME_TYPES)
                            ->maxSize(10240),
                        TextInput::make('name')
                            ->label('Document Name')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        /** @var QuotationEvaluation $record */
                        $record = $this->record;
                        $file = $data['document'] ?? null;
                        if (is_array($file)) {
                            $file = $file[0] ?? null;
                        }
                        if ($file && is_string($file)) {
                            $attached = app(AttachUploadedFiles::class)->execute($record, [$file], 'documents', QuotationEvaluation::DOCUMENTS_UPLOAD_DIRECTORY);
                            $record->refresh();

                            $media = $attached[0] ?? null;
                            if ($media !== null) {
                                $name = $data['name'] ?? null;
                                // Preserve the pre-convergence naming: custom name when given,
                                // otherwise the file's basename (extension included).
                                $media->update(['name' => is_string($name) && $name !== '' ? $name : $media->file_name]);
                            }

                            Notification::make()
                                ->title('Document uploaded')
                                ->success()
                                ->send();
                            $this->refresh();
                        }
                    }),
                DeleteAction::make(),
            ]),
        ];
    }

    /**
     * The announce action is offered at (and gated by) QE approval: the QE
     * must be fully approved, the round not yet announced, and evaluated
     * quotes must exist to announce.
     */
    private function canAnnounceRfqOutcomes(QuotationEvaluation $record): bool
    {
        if ($record->status !== QEStatus::APPROVED) {
            return false;
        }

        $request = $record->request;

        if ($request === null || $request->rfqOutcomesAnnounced()) {
            return false;
        }

        return $request->supplierQuotes()
            ->whereIn('status', [SupplierQuoteStatus::RECEIVED, SupplierQuoteStatus::SELECTED])
            ->whereNull('declined_at')
            ->exists();
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
                            TextEntry::make('status')
                                ->label('Status QE')
                                ->badge(),
                            TextEntry::make('description')
                                ->label('Description')
                                ->placeholder('—'),
                        ])
                        ->columns(4),
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
                        ViewEntry::make('id')
                            ->label('')
                            ->view('filament.infolists.components.qe-supplier-info'),
                    ])
                    ->columnSpan('full')
                    ->collapsible(),

                Section::make('Central Purchasing')
                    ->description('Approval workflow')
                    ->schema([
                        TextEntry::make('prepared_by_id')
                            ->label('Prepared By')
                            ->getStateUsing(function (QuotationEvaluation $record): ?string {
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
                            ->url(function (QuotationEvaluation $record): ?string {
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
                            ->getStateUsing(function (QuotationEvaluation $record): ?string {
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
                            ->formatStateUsing(function (?string $state, ?QuotationEvaluation $record): HtmlString|string|null {
                                if ($state === null || $record === null) {
                                    return $state;
                                }

                                // Add approved badge if approved
                                if ($record->hasDeptHeadSalesApproved()) {
                                    $approvedDate = $record->dept_head_sales_approved_at?->format('M j, Y');

                                    return new HtmlString(
                                        $state.' <span style="display: inline-block; padding: 2px 8px; background-color: #10b981; color: white; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; margin-left: 4px;">approved</span><br/>'.
                                        ($approvedDate ? ' <span style="font-size: 0.75rem; color: #6b7280;">('.$approvedDate.')</span>' : '')
                                    );
                                }

                                return $state;
                            })
                            ->placeholder('—')
                            ->url(function (QuotationEvaluation $record): ?string {
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
                            ->getStateUsing(function (QuotationEvaluation $record): ?string {
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
                            ->formatStateUsing(function (?string $state, ?QuotationEvaluation $record): HtmlString|string|null {
                                if ($state === null || $record === null) {
                                    return $state;
                                }

                                // Add approved badge if approved
                                if ($record->hasDeputyDirectorApproved()) {
                                    $approvedDate = $record->deputy_director_approved_at?->format('M j, Y');

                                    return new HtmlString(
                                        $state.' <span style="display: inline-block; padding: 2px 8px; background-color: #10b981; color: white; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; margin-left: 4px;">approved</span><br/>'.
                                        ($approvedDate ? ' <span style="font-size: 0.75rem; color: #6b7280;">('.$approvedDate.')</span>' : '')
                                    );
                                }

                                return $state;
                            })
                            ->placeholder('—')
                            ->url(function (QuotationEvaluation $record): ?string {
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
                            ->getStateUsing(function (QuotationEvaluation $record): ?string {
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
                            ->formatStateUsing(function (?string $state, ?QuotationEvaluation $record): HtmlString|string|null {
                                if ($state === null || $record === null) {
                                    return $state;
                                }

                                // Add approved badge if approved
                                if ($record->hasDirectorApproved()) {
                                    $approvedDate = $record->director_approved_at?->format('M j, Y');

                                    return new HtmlString(
                                        $state.' <span style="display: inline-block; padding: 2px 8px; background-color: #10b981; color: white; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; margin-left: 4px;">approved</span><br/>'.
                                        ($approvedDate ? ' <span style="font-size: 0.75rem; color: #6b7280;">('.$approvedDate.')</span>' : '')
                                    );
                                }

                                return $state;
                            })
                            ->placeholder('—')
                            ->url(function (QuotationEvaluation $record): ?string {
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

                Section::make('Documents')
                    ->schema([
                        ViewEntry::make('documents')
                            ->label('')
                            ->view('filament.infolists.components.document-list'),
                    ])
                    ->columnSpan('full')
                    ->collapsible(),
            ]);
    }
}
