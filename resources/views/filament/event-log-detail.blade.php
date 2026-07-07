@php
    /** @var \App\Models\ActivityLog $activity */
    $properties = $activity->properties;
    $newValues = (array) $properties->get('attributes', []);
    $oldValues = (array) $properties->get('old', []);
    $keys = array_keys($newValues + $oldValues);

    $format = function ($value): string {
        if (is_null($value)) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
        }
        return trim((string) $value) === '' ? '—' : (string) $value;
    };

    $recordLabel = $activity->subject_type
        ? \Illuminate\Support\Str::headline($activity->subject_type) . ' #' . $activity->subject_id
        : '—';
@endphp

<div class="space-y-6 text-sm">
    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Actor</dt>
            <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $activity->actor_type?->getLabel() ?? 'System' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">User</dt>
            <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $activity->causer?->name ?? 'System' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Event</dt>
            <dd class="mt-1 font-medium capitalize text-gray-950 dark:text-white">{{ $activity->event ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Record</dt>
            <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $recordLabel }}</dd>
        </div>
        <div class="col-span-2">
            <dt class="text-gray-500 dark:text-gray-400">When</dt>
            <dd class="mt-1 font-medium text-gray-950 dark:text-white">
                {{ $activity->created_at?->format('M j, Y H:i:s') }}
                <span class="text-gray-400 dark:text-gray-500">({{ $activity->created_at?->diffForHumans() }})</span>
            </dd>
        </div>
    </dl>

    @if (count($keys) > 0)
        <div class="overflow-x-auto rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">Field</th>
                        <th class="px-4 py-2 font-medium">From</th>
                        <th class="px-4 py-2 font-medium">To</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($keys as $key)
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-950 dark:text-white">{{ \Illuminate\Support\Str::headline($key) }}</td>
                            <td class="px-4 py-2 text-gray-500 line-through decoration-gray-300 dark:text-gray-400">{{ $format($oldValues[$key] ?? null) }}</td>
                            <td class="px-4 py-2 text-gray-950 dark:text-white">{{ $format($newValues[$key] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-gray-500 dark:text-gray-400">No field-level changes were recorded for this event.</p>
    @endif
</div>
