<?php

declare(strict_types=1);

namespace App\Filament\Customer\Resources\CustomerRequestResource\Pages;

use App\Actions\CustomerPortal\NotifyTeamOfPortalRequest;
use App\Actions\Media\AttachUploadedFiles;
use App\Enums\ItemType;
use App\Enums\RequestStage;
use App\Enums\RequestSubmissionMethod;
use App\Filament\Customer\Resources\CustomerRequestResource;
use App\Filament\Customer\Resources\CustomerRequestResource\Schemas\CustomerRequestForm;
use App\Models\RequestItem;
use App\Models\UnitOfMeasure;
use App\Services\Portal\CustomerPortalContext;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

final class CreateCustomerRequest extends CreateRecord
{
    protected static string $resource = CustomerRequestResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(CustomerRequestForm::components())
            ->columns(1);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $portalContext = app(CustomerPortalContext::class);
        $method = RequestSubmissionMethod::from($data['submission_method_choice'] ?? RequestSubmissionMethod::MANUAL->value);

        unset($data['items'], $data['attachment_files'], $data['submission_method_choice']);

        return [
            ...$data,
            'team_id' => $portalContext->teamId(),
            'buyer_id' => $portalContext->companyId(),
            'stage' => RequestStage::DRAFT,
            'submission_method' => $method,
            'submitted_at' => now(),
            'submitted_by_user_id' => auth()->id(),
            'requested_at' => now()->toDateString(),
            'creator_id' => auth()->id(),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $formState = $this->form->getState();
        $method = RequestSubmissionMethod::from($formState['submission_method_choice'] ?? RequestSubmissionMethod::MANUAL->value);

        /** @var \App\Models\Request $record */
        $record = parent::handleRecordCreation($data);

        if ($method === RequestSubmissionMethod::MANUAL) {
            $items = $formState['items'] ?? [];

            foreach ($items as $index => $item) {
                $uom = UnitOfMeasure::query()->find($item['unit_of_measure_id']);

                RequestItem::query()->create([
                    'request_id' => $record->getKey(),
                    'description' => $item['description'],
                    'item_type' => $item['item_type'] ?? ItemType::GOODS,
                    'quantity' => $item['quantity'],
                    'unit_of_measure_id' => $item['unit_of_measure_id'],
                    'unit' => $uom?->code ?? 'pcs',
                    'sort_order' => $index,
                ]);
            }
        }

        if ($method === RequestSubmissionMethod::DOCUMENT) {
            app(AttachUploadedFiles::class)->execute($record, $formState['attachment_files'] ?? [], 'attachments', CustomerRequestForm::ATTACHMENTS_UPLOAD_DIRECTORY);
        }

        app(NotifyTeamOfPortalRequest::class)->execute($record);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return CustomerRequestResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
