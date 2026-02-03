<div class="space-y-4">
    @forelse($approvals as $approval)
        <div class="border rounded-lg p-4 space-y-2">
            <div class="flex items-center justify-between">
                <div class="font-semibold text-gray-900 dark:text-gray-100">
                    {{ $approval->user->name ?? 'Unknown User' }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $approval->approved_at ? $approval->approved_at->format('M j, Y g:i A') : '—' }}
                </div>
            </div>
            <div class="text-sm text-gray-700 dark:text-gray-300">
                <span class="font-medium">Notes:</span>
                <span class="ml-2">{{ $approval->notes ?? 'No notes provided' }}</span>
            </div>
        </div>
    @empty
        <div class="text-center text-gray-500 dark:text-gray-400 py-8">
            No approvals found.
        </div>
    @endforelse
</div>
