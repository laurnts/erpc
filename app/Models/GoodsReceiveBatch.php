<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $request_id
 * @property int $supplier_order_id
 * @property int $user_id
 * @property array $media_ids
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Request $request
 * @property-read SupplierOrder $supplierOrder
 * @property-read User $user
 */
final class GoodsReceiveBatch extends Model
{
    protected $fillable = [
        'request_id',
        'supplier_order_id',
        'user_id',
        'media_ids',
    ];

    protected function casts(): array
    {
        return [
            'media_ids' => 'array',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function supplierOrder(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the Media models in this batch (from the request's goods_receive collection).
     *
     * @return \Illuminate\Support\Collection<int, Media>
     */
    public function getMediaRecords(): \Illuminate\Support\Collection
    {
        if (empty($this->media_ids)) {
            return collect();
        }

        $media = Media::query()->whereIn('id', $this->media_ids)->get();

        return collect($this->media_ids)->map(fn ($id) => $media->firstWhere('id', $id))->filter()->values();
    }

    /**
     * Get the first media record (for display name / link).
     */
    public function getFirstMedia(): ?Media
    {
        return $this->getMediaRecords()->first();
    }
}
