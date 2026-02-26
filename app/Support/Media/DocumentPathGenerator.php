<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\BuyerQuote;
use App\Models\ProfitAndLoss;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\SupplierQuote;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Path generator for document uploads: stores files under dedicated folders per feature
 * e.g. storage/app/supplierquote/{id}/uploaded_document_files/
 */
final class DocumentPathGenerator implements PathGenerator
{
    private const SUBFOLDER = 'uploaded_document_files';

    /**
     * Map of model_type + collection_name to folder name (relative to disk root).
     *
     * @var array<string, array<string, string>>
     */
    private static function folderMap(): array
    {
        return [
            SupplierQuote::class => [
                'quotation' => 'supplierquote',
            ],
            BuyerQuote::class => [
                'buyer_po' => 'buyerquote',
            ],
            SupplierOrder::class => [
                'documents' => 'supplierorder',
            ],
            Request::class => [
                'goods_receive' => 'goodreceive',
                'completion_reports' => 'completionreports',
                'attachments' => 'attachments',
            ],
            QuotationEvaluation::class => [
                'documents' => 'quotationevaluation',
            ],
            ProfitAndLoss::class => [
                'documents' => 'profitandloss',
            ],
        ];
    }

    /**
     * Resolve model_type to class name (app uses morph map, so model_type can be alias e.g. 'supplier_quote').
     */
    private static function resolveModelClass(Media $media): string
    {
        $type = $media->model_type;
        $resolved = Relation::getMorphedModel($type);

        return $resolved ?? $type;
    }

    private static function getFolderName(Media $media): ?string
    {
        $modelClass = self::resolveModelClass($media);
        $collectionName = $media->collection_name;

        foreach (self::folderMap() as $mappedClass => $collections) {
            if (! is_a($modelClass, $mappedClass, true)) {
                continue;
            }
            if (isset($collections[$collectionName])) {
                return $collections[$collectionName];
            }
        }

        return null;
    }

    /** Custom property set on new uploads so they use the dedicated path; avoids breaking existing files. */
    public const PATH_VERSION_PROPERTY = 'path_version';

    public const PATH_VERSION_V2 = 2;

    /**
     * Use dedicated path when: (a) media on 'local' and model was always public (new uploads get local),
     * or (b) media has path_version so we know it was stored with the new structure.
     * Existing media on 'public' or without path_version keep legacy path {id}/.
     */
    private static function useDedicatedPath(Media $media): bool
    {
        if ($media->disk !== 'local') {
            return false;
        }
        $pathVersion = $media->getCustomProperty(self::PATH_VERSION_PROPERTY);
        if ($pathVersion === self::PATH_VERSION_V2 || $pathVersion === (string) self::PATH_VERSION_V2) {
            return true;
        }
        $folder = self::getFolderName($media);
        if ($folder === null) {
            return false;
        }
        $modelClass = self::resolveModelClass($media);
        return $modelClass === SupplierOrder::class
            || $modelClass === Request::class
            || $modelClass === QuotationEvaluation::class
            || $modelClass === ProfitAndLoss::class;
    }

    public function getPath(Media $media): string
    {
        if (! self::useDedicatedPath($media)) {
            return $media->getKey().'/';
        }
        $folder = self::getFolderName($media);
        if ($folder !== null) {
            return $folder.'/'.$media->getKey().'/'.self::SUBFOLDER.'/';
        }

        return $media->getKey().'/';
    }

    public function getPathForConversions(Media $media): string
    {
        if (! self::useDedicatedPath($media)) {
            return $media->getKey().'/conversions/';
        }
        $folder = self::getFolderName($media);
        if ($folder !== null) {
            return $folder.'/'.$media->getKey().'/'.self::SUBFOLDER.'/conversions/';
        }

        return $media->getKey().'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        if (! self::useDedicatedPath($media)) {
            return $media->getKey().'/responsive-images/';
        }
        $folder = self::getFolderName($media);
        if ($folder !== null) {
            return $folder.'/'.$media->getKey().'/'.self::SUBFOLDER.'/responsive-images/';
        }

        return $media->getKey().'/responsive-images/';
    }
}
