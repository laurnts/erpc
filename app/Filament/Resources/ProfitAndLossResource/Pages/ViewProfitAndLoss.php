<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProfitAndLossResource\Pages;

use App\Actions\Media\AttachUploadedFiles;
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

final class ViewProfitAndLoss extends ViewRecord
{
    /** @var class-string<ProfitAndLossResource> */
    protected static string $resource = ProfitAndLossResource::class;

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
                ->modalHeading('Approve Profit & Loss?')
                ->modalDescription(fn (ProfitAndLoss $record): string => 'Are you sure you want to approve this Profit & Loss document?')
                ->action(function (ProfitAndLoss $record): void {
                    /** @var \App\Models\User $user */
                    $user = auth()->user();

                    try {
                        $record->approve($user);

                        Notification::make()
                            ->title('Profit & Loss approved')
                            ->body('Your approval has been recorded.')
                            ->success()
                            ->send();

                        // Refresh the page to show updated status
                        $this->redirect(ProfitAndLossResource::getUrl('view', ['record' => $record]));
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Cannot approve')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (ProfitAndLoss $record): bool => $record->canBeApprovedBy(auth()->user())),
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
                            headers: ['Content-Type' => 'application/pdf'],
                        );
                    }),
                Action::make('uploadDocument')
                    ->label('Upload Document')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        FileUpload::make('document')
                            ->label('Document')
                            ->required()
                            ->disk('local')
                            ->directory(ProfitAndLoss::DOCUMENTS_UPLOAD_DIRECTORY)
                            ->visibility('private')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'image/png',
                                'image/jpeg',
                                'image/jpg',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->maxSize(10240),
                        TextInput::make('name')
                            ->label('Document Name')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        /** @var ProfitAndLoss $record */
                        $record = $this->record;
                        $file = $data['document'] ?? null;
                        if (is_array($file)) {
                            $file = $file[0] ?? null;
                        }
                        if ($file && is_string($file)) {
                            $attached = app(AttachUploadedFiles::class)->execute($record, [$file], 'documents', ProfitAndLoss::DOCUMENTS_UPLOAD_DIRECTORY);
                            $record->refresh();

                            $name = $data['name'] ?? null;
                            if (is_string($name) && $name !== '') {
                                ($attached[0] ?? null)?->update(['name' => $name]);
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
                                ->label('Status PNL')
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
                            ->formatStateUsing(function (?string $state, ?\App\Models\ProfitAndLoss $record): HtmlString|string|null {
                                if ($state === null || $record === null) {
                                    return $state;
                                }

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
                            ->formatStateUsing(function (?string $state, ?\App\Models\ProfitAndLoss $record): HtmlString|string|null {
                                if ($state === null || $record === null) {
                                    return $state;
                                }

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
                            ->formatStateUsing(function (?string $state, ?\App\Models\ProfitAndLoss $record): HtmlString|string|null {
                                if ($state === null || $record === null) {
                                    return $state;
                                }

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
