<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Data\TimelineEntry;
use App\Livewire\Concerns\AuthorizesLivewireActions;
use App\Models\ActivityLog;
use App\Models\Request;
use App\Services\Timeline\RequestTimelineSource;
use App\Services\Timeline\TimelineParty;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Collapsible History timeline on the internal request view (design D6):
 * day-grouped, paginated, per-save summary lines only — exact field diffs
 * live in the shared event-log detail modal, never inline.
 */
final class RequestHistoryTimeline extends BaseLivewireComponent
{
    use AuthorizesLivewireActions;

    public Request $request;

    public int $page = 1;

    public int $perPage = 25;

    public function mount(Request $request): void
    {
        $this->ensureTeamOwnership($request);

        $this->request = $request;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    /**
     * Shared event-log detail modal (reuses the Event Logs resource view),
     * guarded so a tampered activity id outside this request's subject tree
     * is rejected.
     */
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
        $paginator = $source->entries($this->request, TimelineParty::staff(), $this->page, $this->perPage);

        if ($this->page > 1 && $this->page > $paginator->lastPage()) {
            $this->page = max(1, $paginator->lastPage());
            $paginator = $source->entries($this->request, TimelineParty::staff(), $this->page, $this->perPage);
        }

        return view('livewire.request-history-timeline', [
            'dayGroups' => $this->groupByDay(collect($paginator->items())),
            'paginator' => $paginator,
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

    /**
     * @param  Collection<int, TimelineEntry>  $entries
     * @return Collection<int, array{label: string, entries: Collection<int, TimelineEntry>}>
     */
    private function groupByDay(Collection $entries): Collection
    {
        return $entries
            ->groupBy(fn (TimelineEntry $entry): string => $entry->occurredAt->toDateString())
            ->map(fn (Collection $dayEntries, string $day): array => [
                'label' => $this->dayLabel($day),
                'entries' => $dayEntries->values(),
            ])
            ->values();
    }

    private function dayLabel(string $day): string
    {
        $date = Carbon::parse($day);

        if ($date->isToday()) {
            return 'Today';
        }

        if ($date->isYesterday()) {
            return 'Yesterday';
        }

        return $date->format('j M Y');
    }
}
