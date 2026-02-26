<?php

declare(strict_types=1);

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Exports\ArticleExporter;
use App\Filament\Imports\ArticleImporter;
use App\Filament\Resources\ArticleResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;
use Override;

final class ListArticles extends ListRecords
{
    /** @var class-string<ArticleResource> */
    protected static string $resource = ArticleResource::class;

    /**
     * Get the actions available on the resource index header.
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ImportAction::make()->importer(ArticleImporter::class),
                ExportAction::make()->exporter(ArticleExporter::class),
            ])
                ->icon('heroicon-o-arrows-up-down')
                ->color('gray')
                ->button()
                ->label('Import / Export')
                ->size(Size::Small),
            CreateAction::make()->icon('heroicon-o-plus')->size(Size::Small),
        ];
    }
}
