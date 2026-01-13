<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RequestItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property string|null $notes
 * @property int $sort_order
 * @property bool $is_matched
 * @property int $request_id
 * @property int|null $article_id
 * @property-read Request $request
 * @property-read Article|null $article
 */
final class RequestItem extends Model
{
    /** @use HasFactory<RequestItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'article_id',
        'description',
        'quantity',
        'unit',
        'notes',
        'sort_order',
        'is_matched',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'unit' => 'pcs',
        'sort_order' => 0,
        'is_matched' => false,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'sort_order' => 'integer',
            'is_matched' => 'boolean',
        ];
    }

    /**
     * The request that owns this item.
     *
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * The article matched to this item.
     *
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Match this item to an article.
     */
    public function matchToArticle(Article $article): void
    {
        $this->article_id = $article->getKey();
        $this->is_matched = true;
        $this->save();
    }

    /**
     * Unmatch this item from its article.
     */
    public function unmatch(): void
    {
        $this->article_id = null;
        $this->is_matched = false;
        $this->save();
    }

    /**
     * Get the display text for this item.
     */
    public function getDisplayTextAttribute(): string
    {
        if ($this->article !== null) {
            return sprintf('[%s] %s', $this->article->code, $this->article->name);
        }

        return $this->description;
    }
}
