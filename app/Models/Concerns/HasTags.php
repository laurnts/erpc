<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Trait for models that can be tagged.
 *
 * @method MorphToMany morphToMany(string $related, string $name, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null, ?string $parentKey = null, ?string $relatedKey = null, bool $inverse = false)
 */
trait HasTags
{
    /**
     * Get all tags for this model.
     *
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }

    /**
     * Sync tags for this model.
     *
     * @param  array<int>  $tagIds
     */
    public function syncTags(array $tagIds): void
    {
        $this->tags()->sync($tagIds);
    }

    /**
     * Attach tags to this model.
     *
     * @param  array<int>  $tagIds
     */
    public function attachTags(array $tagIds): void
    {
        $this->tags()->attach($tagIds);
    }

    /**
     * Detach tags from this model.
     *
     * @param  array<int>|null  $tagIds
     */
    public function detachTags(?array $tagIds = null): void
    {
        if ($tagIds === null) {
            $this->tags()->detach();
        } else {
            $this->tags()->detach($tagIds);
        }
    }

    /**
     * Check if this model has a specific tag.
     */
    public function hasTag(int|Tag $tag): bool
    {
        $tagId = $tag instanceof Tag ? $tag->id : $tag;

        return $this->tags()->where('tags.id', $tagId)->exists();
    }
}
