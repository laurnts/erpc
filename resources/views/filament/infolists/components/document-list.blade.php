@php
    $record = $getRecord();
    $mediaItems = $record && method_exists($record, 'getMedia')
        ? $record->getMedia('documents')
        : collect();
@endphp

@if($mediaItems->isNotEmpty())
    <div class="space-y-2">
        @foreach($mediaItems as $media)
            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $media->name }}
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
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                    Open
                </a>
            </div>
        @endforeach
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">No documents uploaded.</p>
@endif
