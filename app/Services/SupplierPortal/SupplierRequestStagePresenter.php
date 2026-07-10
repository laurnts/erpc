<?php

declare(strict_types=1);

namespace App\Services\SupplierPortal;

use App\Models\Request;
use App\Services\Portal\RequestEffectiveStageResolver;
use App\Services\Portal\RequestWorkflowTimelinePresenter;

final readonly class SupplierRequestStagePresenter
{
    public function __construct(
        private RequestWorkflowTimelinePresenter $timelinePresenter,
        private RequestEffectiveStageResolver $stageResolver,
    ) {}

    /**
     * @return list<array{stage: \App\Enums\RequestStage, label: string, completed: bool, current: bool}>
     */
    public function timeline(Request $request): array
    {
        return $this->timelinePresenter->timeline($request, $this->stageResolver->effectiveStage($request));
    }
}
