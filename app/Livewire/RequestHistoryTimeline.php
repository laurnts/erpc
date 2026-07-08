<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Data\TimelineEntry;
use App\Enums\TimelineHistoryFilter;
use App\Livewire\Concerns\AuthorizesLivewireActions;
use App\Models\ActivityLog;
use App\Models\Request;
use App\Services\Timeline\RequestTimelineSource;
use App\Services\Timeline\TimelineFeedPresenter;
use App\Services\Timeline\TimelineParty;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\On;

/**
 * Per-request history feed: compact glanceable sidebar or full searchable log.
 */
final class RequestHistoryTimeline extends BaseLivewireComponent
{
    use AuthorizesLivewireActions;

    public const int COMPACT_LIMIT = 20;

    public Request $request;

    public bool $compact = false;

    public bool $showComposer = true;

    public string $filter = 'all';

    public string $search = '';

    public int $page = 1;

    public int $perPage = 25;

    /** @var list<string> */
    public array $expandedDays = [];

    /** @var list<string> */
    public array $expandedClusters = [];

    public function mount(Request $request, bool $compact = false, bool $showComposer = true): void
    {
        $this->ensureTeamOwnership($request);

        $this->request = $request;
        $this->compact = $compact;
        $this->showComposer = $showComposer;

        if ($compact) {
            $this->perPage = self::COMPACT_LIMIT;
        }

        $this->expandedDays = [now()->toDateString()];
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->page = 1;
        $this->expandedClusters = [];
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function toggleDay(string $dayKey): void
    {
        if (in_array($dayKey, $this->expandedDays, true)) {
            $this->expandedDays = array_values(array_filter(
                $this->expandedDays,
                fn (string $day): bool => $day !== $dayKey,
            ));
        } else {
            $this->expandedDays[] = $dayKey;
        }
    }

    public function toggleCluster(string $clusterKey): void
    {
        if (in_array($clusterKey, $this->expandedClusters, true)) {
            $this->expandedClusters = array_values(array_filter(
                $this->expandedClusters,
                fn (string $key): bool => $key !== $clusterKey,
            ));
        } else {
            $this->expandedClusters[] = $clusterKey;
        }
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    #[On('note-posted')]
    public function refreshAfterNote(): void
    {
        $this->page = 1;
    }

    public function viewFullLogAction(): Action
    {
        return Action::make('viewFullLog')
            ->label('View full activity log')
            ->link()
            ->slideOver()
            ->modalHeading('Activities · '.$this->request->request_number)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn (): View => view('livewire.request-activity-log-full', [
                'request' => $this->request,
            ]));
    }

    public function detailsAction(): Action
    {
        return Action::make('details')
            ->label('View details')
            ->link()
            ->modalHeading('Event details')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn (array $arguments): View => view('filament.event-log-detail', [
                'activity' => $this->resolveActivity((int) ($arguments['activity'] ?? 0)),
            ]));
    }

    public function render(): View
    {
        $source = app(RequestTimelineSource::class);
        $presenter = app(TimelineFeedPresenter::class);
        $filter = TimelineHistoryFilter::tryFrom($this->filter) ?? TimelineHistoryFilter::All;

        $allEntries = collect($source->entries($this->request, TimelineParty::staff(), 1, PHP_INT_MAX)->items());
        $filtered = $presenter->filter(
            $allEntries,
            $filter,
            ! $this->compact && $this->search !== '' ? $this->search : null,
        );

        $totalCount = $filtered->count();

        if ($this->compact) {
            $pageEntries = $filtered->take(self::COMPACT_LIMIT);
            $paginator = null;
            $hasMore = $totalCount > self::COMPACT_LIMIT;
        } else {
            $page = max(1, $this->page);
            $lastPage = max(1, (int) ceil($totalCount / $this->perPage));

            if ($page > $lastPage) {
                $page = $lastPage;
                $this->page = $page;
            }

            $pageEntries = $filtered->forPage($page, $this->perPage)->values();
            $paginator = new LengthAwarePaginator($pageEntries, $totalCount, $this->perPage, $page);
            $hasMore = false;
        }

        return view('livewire.request-history-timeline', [
            'dayGroups' => $presenter->groupByDay($pageEntries),
            'paginator' => $paginator,
            'hasMore' => $hasMore,
            'totalCount' => $totalCount,
            'filters' => TimelineHistoryFilter::chips(),
            'activeFilter' => $filter,
        ]);
    }

    private function resolveActivity(int $activityId): ActivityLog
    {
        /** @var ActivityLog $activity */
        $activity = ActivityLog::query()->with(['causer', 'subject'])->findOrFail($activityId);

        abort_unless(
            app(RequestTimelineSource::class)->allowsActivity($this->request, TimelineParty::staff(), $activity),
            403,
        );

        return $activity;
    }
}
