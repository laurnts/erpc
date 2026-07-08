@php
    use App\Enums\RequestSubmissionMethod;
    use App\Services\CustomerPortal\CustomerRequestStagePresenter;

    /** @var \App\Models\Request|null $record */
    $record = $getRecord();
    $presenter = app(CustomerRequestStagePresenter::class);
@endphp

@if ($record)
    <dl class="space-y-3 text-sm">
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</dt>
            <dd class="mt-1">
                <x-filament::badge
                    :color="match ($record->item_type_summary) {
                        'Goods' => 'primary',
                        'Services' => 'success',
                        'Mixed' => 'warning',
                        default => 'gray',
                    }"
                >
                    {{ $record->item_type_summary }}
                </x-filament::badge>
            </dd>
        </div>

        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Submitted at</dt>
            <dd class="mt-1 text-gray-900 dark:text-white">
                {{ $record->submitted_at?->format('M j, Y, H:i') ?? '—' }}
            </dd>
        </div>

        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Required date</dt>
            <dd class="mt-1 text-gray-900 dark:text-white">
                {{ $record->required_by?->format('M j, Y') ?? '—' }}
            </dd>
        </div>

        @if ($record->isPortalSubmission())
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Submission method</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">
                    {{ $record->submission_method?->getLabel() ?? '—' }}
                </dd>
            </div>
        @endif

        @if ($record->submission_method === RequestSubmissionMethod::DOCUMENT)
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Notes</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">
                    {{ filled($record->description) ? $record->description : '—' }}
                </dd>
            </div>
        @endif

        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Project</dt>
            <dd class="mt-1 text-gray-900 dark:text-white">
                {{ $record->project?->name ?? '—' }}
            </dd>
        </div>
    </dl>
@endif
