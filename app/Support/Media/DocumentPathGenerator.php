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
final readonly class DocumentPathGenerator implements PathGenerator
{
    private const string SUBFOLDER = 'uploaded_document_files';

    /**
     * Map of model_type + collection_name to folder name (relative to disk root).
     *
     * @return array<class-string, array<string, string>>
     */
    private function folderMap(): array
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
    private function resolveModelClass(Media $media): string
    {
        $type = $media->model_type;
        $resolved = Relation::getMorphedModel($type);

        return $resolved ?? $type;
    }

    private function getFolderName(Media $media): ?string
    {
        $modelClass = $this->resolveModelClass($media);
        $collectionName = $media->collection_name;

        foreach ($this->folderMap() as $mappedClass => $collections) {
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
    public const string PATH_VERSION_PROPERTY = 'path_version';

    public const int PATH_VERSION_V2 = 2;

    public const int PATH_VERSION_V3 = 3;

    /** Custom property holding the fully-resolved v3 path prefix stamped at attach time. */
    public const string PATH_PREFIX_PROPERTY = 'path_prefix';

    /**
     * Resolve the stamped v3 prefix for the media, or null when the media is not a
     * complete v3 upload. This never queries the database: it reads custom properties only.
     */
    private function v3Prefix(Media $media): ?string
    {
        $version = $media->getCustomProperty(self::PATH_VERSION_PROPERTY);
        if ($version !== self::PATH_VERSION_V3 && $version !== (string) self::PATH_VERSION_V3) {
            return null;
        }

        $prefix = $media->getCustomProperty(self::PATH_PREFIX_PROPERTY);
        if (! is_string($prefix) || $prefix === '') {
            return null;
        }

        return $prefix;
    }

    /**
     * Use dedicated path when: (a) media on 'local' and model was always public (new uploads get local),
     * or (b) media has path_version so we know it was stored with the new structure.
     * Existing media on 'public' or without path_version keep legacy path {id}/.
     */
    private function useDedicatedPath(Media $media): bool
    {
        if ($media->disk !== 'local') {
            return false;
        }
        $pathVersion = $media->getCustomProperty(self::PATH_VERSION_PROPERTY);
        if ($pathVersion === self::PATH_VERSION_V2 || $pathVersion === (string) self::PATH_VERSION_V2) {
            return true;
        }
        $folder = $this->getFolderName($media);
        if ($folder === null) {
            return false;
        }
        $modelClass = $this->resolveModelClass($media);

        return in_array($modelClass, [SupplierOrder::class, Request::class, QuotationEvaluation::class, ProfitAndLoss::class], true);
    }

    public function getPath(Media $media): string
    {
        $v3Prefix = $this->v3Prefix($media);
        if ($v3Prefix !== null) {
            return $v3Prefix.'/'.$media->getKey().'/';
        }
        if (! $this->useDedicatedPath($media)) {
            return $media->getKey().'/';
        }
        $folder = $this->getFolderName($media);
        if ($folder !== null) {
            return $folder.'/'.$media->getKey().'/'.self::SUBFOLDER.'/';
        }

        return $media->getKey().'/';
    }

    public function getPathForConversions(Media $media): string
    {
        $v3Prefix = $this->v3Prefix($media);
        if ($v3Prefix !== null) {
            return $v3Prefix.'/'.$media->getKey().'/conversions/';
        }
        if (! $this->useDedicatedPath($media)) {
            return $media->getKey().'/conversions/';
        }
        $folder = $this->getFolderName($media);
        if ($folder !== null) {
            return $folder.'/'.$media->getKey().'/'.self::SUBFOLDER.'/conversions/';
        }

        return $media->getKey().'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        $v3Prefix = $this->v3Prefix($media);
        if ($v3Prefix !== null) {
            return $v3Prefix.'/'.$media->getKey().'/responsive-images/';
        }
        if (! $this->useDedicatedPath($media)) {
            return $media->getKey().'/responsive-images/';
        }
        $folder = $this->getFolderName($media);
        if ($folder !== null) {
            return $folder.'/'.$media->getKey().'/'.self::SUBFOLDER.'/responsive-images/';
        }

        return $media->getKey().'/responsive-images/';
    }
}
