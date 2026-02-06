<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuotationEvaluationResource\Pages;

use App\Filament\Resources\QuotationEvaluationResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditQuotationEvaluation extends EditRecord
{
    /** @var class-string<QuotationEvaluationResource> */
    protected static string $resource = QuotationEvaluationResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        \Log::info("[EditQuotationEvaluation] mount called", [
            'record_id' => $this->record->id,
            'request_loaded' => $this->record->relationLoaded('request'),
            'request_id' => $this->record->request_id ?? null,
        ]);
        
        // Ensure request relationship is loaded so buyer filtering works
        $this->record->load('request');
        
        \Log::info("[EditQuotationEvaluation] Request relationship loaded in mount", [
            'request_loaded' => $this->record->relationLoaded('request'),
            'request_buyer_id' => $this->record->request?->buyer_id ?? null,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ViewAction::make(),
                DeleteAction::make(),
            ]),
        ];
    }
}
