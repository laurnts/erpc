<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\TagObserver;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property string $name
 * @property string $color
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property-read string $created_by
 */
#[ObservedBy(TagObserver::class)]
final class Tag extends Model
{
    use HasCreator;

    /** @use HasFactory<TagFactory> */
    use HasFactory;

    use HasTeam;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'color',
        'description',
        'sort_order',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'color' => 'gray',
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get all companies that have this tag.
     *
     * @return MorphToMany<Company, $this>
     */
    public function companies(): MorphToMany
    {
        return $this->morphedByMany(Company::class, 'taggable');
    }

    /**
     * Get all articles that have this tag.
     *
     * @return MorphToMany<Article, $this>
     */
    public function articles(): MorphToMany
    {
        return $this->morphedByMany(Article::class, 'taggable');
    }
}
