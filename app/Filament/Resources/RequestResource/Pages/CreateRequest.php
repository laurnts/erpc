<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\Pages;

use App\Actions\Media\AttachUploadedFiles;
use App\Filament\Resources\RequestResource;
use App\Models\Request;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

final class CreateRequest extends CreateRecord
{
    /** @var class-string<RequestResource> */
    protected static string $resource = RequestResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(RequestResource::getFormSchema(isCreate: true))
            ->columns(1);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $files = $this->form->getState()['proof_files'] ?? [];
        unset($data['proof_files']);

        /** @var \App\Models\Request $record */
        $record = parent::handleRecordCreation($data);

        app(AttachUploadedFiles::class)->execute($record, $files, 'attachments', Request::PROOF_UPLOAD_DIRECTORY);

        return $record;
    }
}
