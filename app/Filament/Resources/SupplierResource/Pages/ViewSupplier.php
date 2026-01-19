<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\SupplierResource\RelationManagers\ArticlesRelationManager;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            ArticlesRelationManager::class,
        ];
    }
}
