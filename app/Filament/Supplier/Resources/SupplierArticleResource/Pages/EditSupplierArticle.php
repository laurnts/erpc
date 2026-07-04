<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Resources\SupplierArticleResource\Pages;

use App\Actions\SupplierPortal\UpdateSupplierArticleOffer;
use App\Filament\Supplier\Resources\SupplierArticleResource;
use App\Models\SupplierArticle;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditSupplierArticle extends EditRecord
{
    protected static string $resource = SupplierArticleResource::class;

    /**
     * No delete or other management actions — suppliers only maintain their
     * own offer fields.
     *
     * @return array<mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Every save routes through the whitelisting action so tampered Livewire
     * payloads can never write staff-owned columns.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var SupplierArticle $record */
        return app(UpdateSupplierArticleOffer::class)->execute($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return SupplierArticleResource::getUrl('index');
    }
}
