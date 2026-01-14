<?php

declare(strict_types=1);

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateArticle extends CreateRecord
{
    /** @var class-string<ArticleResource> */
    protected static string $resource = ArticleResource::class;
}
