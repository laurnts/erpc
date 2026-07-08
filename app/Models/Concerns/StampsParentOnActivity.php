<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Article;
use App\Models\Company;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Support\ActivityLogContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity;

/**
 * Enriches a line-item's logged activity with the context a finance reviewer
 * needs to read a line change without opening the document (design D1/D5):
 *
 *  - `parent_type` / `parent_id` — the header morph alias + id, so the request
 *    timeline can match a line entry to its parent even after the line row is
 *    hard-deleted (the id is gone, but the parent pointer survives in
 *    properties);
 *  - `line_label` — a human handle (description, else article, else sort_order);
 *  - `labels` — audited FK fields (article/tax code/supplier/UoM) resolved to
 *    old→new human labels so a bait-and-switch reads plainly.
 *
 * It also gates the money row: an observer that restates derived figures
 * (`line_total`, `margin_percent`) on a cosmetic edit must not manufacture an
 * audit row when no causal input actually moved (design D3, task 3.4). The
 * `deleted` snapshot is left to Spatie, which already records the full audited
 * set into old-values on delete.
 *
 * Used ALONGSIDE {@see LogsErpActivity}; the item model resolves the
 * `isLogEmpty` trait conflict in favour of this concern.
 */
trait StampsParentOnActivity
{
    /**
     * The header morph alias this line belongs under (e.g. 'buyer_quote').
     */
    abstract protected function activityParentAlias(): string;

    /**
     * The foreign-key column pointing at the parent header (e.g. 'buyer_quote_id').
     */
    abstract protected function activityParentIdColumn(): string;

    /**
     * Stamp parent context + resolved FK labels onto the pending activity.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $properties = $activity->properties instanceof Collection
            ? $activity->properties
            : new Collection((array) $activity->properties);

        $properties->put('parent_type', $this->activityParentAlias());
        $properties->put('parent_id', $this->activityParentId());
        $properties->put('line_label', $this->activityLineLabel());

        $labels = $this->resolveActivityLabels($properties);

        if ($labels !== []) {
            $properties->put('labels', $labels);
        }

        $activity->properties = $properties;

        /*
         * Portal actors carry no Filament tenant and often no currentTeam, so
         * the ambient ActivityLogContext cannot stamp team_id for them. The
         * parent header always owns a team; stamping it here keeps every item
         * row team-scoped (design D7's goal: no orphan rows the team-scoped
         * viewer can never surface).
         */
        if ($activity->getAttribute('team_id') === null) {
            $activity->setAttribute('team_id', $this->resolveActivityTeamId());
        }
    }

    /**
     * Suppress a row whose only changed fields are derived money restatements
     * with no causal input behind them (observer false positive), while always
     * keeping genuine creations, deletions, and causal edits.
     *
     * @param  array{attributes?: array<string, mixed>, old?: array<string, mixed>}  $changes
     */
    public function isLogEmpty(array $changes): bool
    {
        if ($this->itemActivityContextSuppressed()) {
            return true;
        }

        $attributes = $changes['attributes'] ?? [];
        $old = $changes['old'] ?? [];

        if ($attributes === [] && $old === []) {
            return true;
        }

        // A deletion carries only the old snapshot; never suppress it.
        if ($attributes === []) {
            return false;
        }

        $derived = $this->derivedActivityAttributes();

        if ($derived === []) {
            return false;
        }

        return array_diff(array_keys($attributes), $derived) === [];
    }

    /**
     * Derived money figures that must not, on their own, justify an audit row.
     *
     * @return list<string>
     */
    protected function derivedActivityAttributes(): array
    {
        return ['line_total', 'margin_percent'];
    }

    /**
     * Item rows are only meaningful inside a team-scoped acting context
     * (design D7): seeders, console imports and queue workers would
     * otherwise flood the log with rows no reviewer caused. The test
     * environment is exempt from the console check — the whole battery runs
     * under `artisan test`. A row whose team cannot be resolved even from
     * its parent header would be invisible to the team-scoped viewer, so it
     * is suppressed rather than written as an orphan.
     */
    protected function itemActivityContextSuppressed(): bool
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return true;
        }

        return $this->resolveActivityTeamId() === null;
    }

    /**
     * The owning team: the parent header's `team_id`, falling back to the
     * ambient context (tenant / authenticated causer) when the parent is
     * already gone (e.g. cascade paths).
     */
    protected function resolveActivityTeamId(): ?int
    {
        $relation = Str::camel(Str::beforeLast($this->activityParentIdColumn(), '_id'));

        $parent = method_exists($this, $relation) ? $this->getRelationValue($relation) : null;

        if ($parent instanceof Model && $parent->getAttribute('team_id') !== null) {
            return (int) $parent->getAttribute('team_id');
        }

        return ActivityLogContext::currentTeamId();
    }

    /**
     * A human handle for this line: its description, else its article, else
     * its position.
     */
    protected function activityLineLabel(): string
    {
        $description = trim((string) ($this->getAttribute('description') ?? ''));

        if ($description !== '') {
            return $description;
        }

        if (method_exists($this, 'article') && $this->getAttribute('article_id') !== null) {
            $article = $this->getAttribute('article');

            if ($article instanceof Article) {
                return sprintf('[%s] %s', $article->code, $article->name);
            }
        }

        return 'Line '.(int) ($this->getAttribute('sort_order') ?? 0);
    }

    private function activityParentId(): int
    {
        return (int) $this->getAttribute($this->activityParentIdColumn());
    }

    /**
     * Resolve every audited FK field present in the diff to an old→new label
     * pair, so ids never reach the reviewer raw.
     *
     * @return array<string, array{old?: string|null, new?: string|null}>
     */
    private function resolveActivityLabels(Collection $properties): array
    {
        $labels = [];

        /** @var array<string, mixed> $attributes */
        $attributes = (array) $properties->get('attributes', []);
        /** @var array<string, mixed> $old */
        $old = (array) $properties->get('old', []);

        foreach (self::activityLabelResolvers() as $field => $resolver) {
            $resolved = [];

            if (array_key_exists($field, $attributes)) {
                $resolved['new'] = $attributes[$field] === null ? null : $resolver($attributes[$field]);
            }

            if (array_key_exists($field, $old)) {
                $resolved['old'] = $old[$field] === null ? null : $resolver($old[$field]);
            }

            if ($resolved !== []) {
                $labels[$field] = $resolved;
            }
        }

        return $labels;
    }

    /**
     * Field => (id -> human label) resolvers for the audited FK levers.
     *
     * @return array<string, callable(mixed): ?string>
     */
    private static function activityLabelResolvers(): array
    {
        return [
            'article_id' => static function (mixed $id): ?string {
                $article = Article::query()->find($id);

                return $article === null ? null : sprintf('[%s] %s', $article->code, $article->name);
            },
            'tax_code_id' => static function (mixed $id): ?string {
                $taxCode = TaxCode::query()->find($id);

                return $taxCode === null ? null : sprintf('[%s] %s', $taxCode->code, $taxCode->name);
            },
            'supplier_id' => static function (mixed $id): ?string {
                $supplier = Company::query()->find($id);

                return $supplier?->name;
            },
            'unit_of_measure_id' => static function (mixed $id): ?string {
                $unit = UnitOfMeasure::query()->find($id);

                return $unit?->label;
            },
        ];
    }
}
