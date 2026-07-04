<?php

declare(strict_types=1);

namespace App\Actions\SupplierPortal;

use App\Actions\Catalog\RefreshArticlePriceReview;
use App\Models\SupplierArticle;
use Illuminate\Support\Arr;

/**
 * The only write path for supplier-owned offer fields on a supplier-article
 * link. The whitelist is the enforcement against tampered payloads — form
 * field absence alone is not; `*_updated_at` stamping happens in the
 * SupplierArticle saving hook.
 */
final readonly class UpdateSupplierArticleOffer
{
    private const array SUPPLIER_WRITABLE = [
        'supplier_price',
        'supplier_price_currency_id',
        'available_quantity',
        'lead_time_days',
    ];

    public function __construct(private RefreshArticlePriceReview $refreshArticlePriceReview) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(SupplierArticle $link, array $attributes): SupplierArticle
    {
        $link->fill(Arr::only($attributes, self::SUPPLIER_WRITABLE));
        $priceChanged = $link->isDirty(['supplier_price', 'supplier_price_currency_id']);
        $link->save();

        if ($priceChanged && $link->article !== null) {
            $this->refreshArticlePriceReview->execute($link->article);
        }

        return $link->refresh();
    }
}
