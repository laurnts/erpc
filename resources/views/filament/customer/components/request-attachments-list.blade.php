@php
    /** @var \App\Models\Request|null $record */
    $record = $getRecord();
    $mediaItems = $record?->getMedia('attachments') ?? collect();
@endphp

@if ($mediaItems->isNotEmpty())
    <div class="space-y-2">
        @foreach ($mediaItems as $media)
            <div class="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="flex min-w-0 items-center gap-3">
                    <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $media->file_name }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ number_format($media->size / 1024, 2) }} KB
                        </p>
                    </div>
                </div>
                <a
                    href="{{ $media->getUrl() }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex shrink-0 items-center px-3 py-1.5 text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                >
                    Download
                </a>
            </div>
        @endforeach
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">
        No documents yet.
    </p>
@endif
