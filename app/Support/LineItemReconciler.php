<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Reconciles a document's line-item relation against the rows submitted by a
 * form, in place, so genuine edits fire Eloquent events (design D2 of
 * add-line-item-activity-logging).
 *
 * Line persistence historically cleared the relation with a query-builder
 * delete and recreated every row, which bypasses model events entirely:
 * deletions were invisible and unchanged rows churned their primary keys. This
 * helper instead matches incoming rows to existing children by primary key:
 *   - a row carrying a key that maps to an existing child is filled + saved
 *     (fires `updated` only when actually dirty);
 *   - existing children absent from the incoming set are deleted as models
 *     (fires `deleted`, preserving any cascade the model owns);
 *   - a row without a matching key is created (fires `created`).
 *
 * The resulting item set (values + hierarchy) is identical to the old
 * delete-and-recreate; only the events and the surviving primary keys differ.
 */
final readonly class LineItemReconciler
{
    /**
     * @template TItem of Model
     *
     * @param  HasMany<TItem, Model>  $relation  the child relation to reconcile (e.g. items() / children())
     * @param  iterable<int|string, array<string, mixed>>  $rows  the submitted rows, in display order
     * @param  callable(array<string, mixed>, int): array<string, mixed>  $mapAttributes
     *                                                                                    maps a submitted row plus its zero-based position to model attributes
     * @param  string  $keyName  the row key holding an existing child's primary key
     * @return Collection<int, TItem> surviving + created models, in incoming order
     */
    public static function reconcile(
        HasMany $relation,
        iterable $rows,
        callable $mapAttributes,
        string $keyName = 'id',
    ): Collection {
        /** @var Collection<int|string, TItem> $existing */
        $existing = $relation->get()->keyBy(static fn (Model $model): int|string => $model->getKey());

        /** @var array<int, int|string> $keptKeys */
        $keptKeys = [];

        /** @var Collection<int, TItem> $result */
        $result = new Collection;

        $position = 0;

        foreach ($rows as $row) {
            $attributes = $mapAttributes($row, $position);
            $position++;

            $incomingKey = $row[$keyName] ?? null;

            if ($incomingKey !== null && $existing->has($incomingKey)) {
                /** @var TItem $model */
                $model = $existing->get($incomingKey);
                $model->fill($attributes);
                $model->save();

                $keptKeys[] = $model->getKey();
                $result->push($model);

                continue;
            }

            /** @var TItem $created */
            $created = $relation->create($attributes);
            $result->push($created);
        }

        foreach ($existing as $key => $model) {
            if (! in_array($key, $keptKeys, true)) {
                $model->delete();
            }
        }

        return $result;
    }
}
