<?php

declare(strict_types=1);

namespace App\Services\Timeline;

use App\Enums\RequestStage;
use App\Services\BuyerPortal\BuyerRequestStagePresenter;
use Illuminate\Support\Str;

/**
 * Presentation redaction for one timeline audience.
 *
 * Deliberately narrow (design D2): the real buyer/supplier protection is
 * subject selection via SubjectRule — a positive allow-list cannot leak
 * what it never selects, and per-model logOnly() means an allow-listed
 * subject's properties never contain supplier cost or margin figures.
 * Redaction only covers the residual presentation concerns: collapsing
 * the causer to a generic label (never a staff person name), re-mapping
 * stage values to party-facing labels, and link allow-listing.
 */
final readonly class RedactionRules
{
    /**
     * @param  string|null  $genericCauserLabel  label rendered instead of the causer name when collapsing
     * @param  list<string>  $allowedLinkRoutePrefixes  route-name prefixes links may resolve to; empty = no links (default deny)
     */
    public function __construct(
        public bool $collapseCauser,
        public ?string $genericCauserLabel,
        public bool $remapStageLabels,
        public array $allowedLinkRoutePrefixes,
    ) {}

    /**
     * Party-facing label for a stage value found in an activity diff.
     */
    public function stageLabel(RequestStage $stage): string
    {
        if (! $this->remapStageLabels) {
            return $stage->getLabel();
        }

        return app(BuyerRequestStagePresenter::class)->labelForStage($stage);
    }

    /**
     * Whether an entry may link to the given named route (default deny).
     */
    public function allowsLinkRoute(?string $routeName): bool
    {
        if ($routeName === null) {
            return false;
        }

        return Str::startsWith($routeName, $this->allowedLinkRoutePrefixes);
    }
}
