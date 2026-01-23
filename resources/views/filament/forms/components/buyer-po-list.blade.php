@php
    /** @var \App\Models\BuyerQuote $record */
    $record = $getRecord();
    
    // Ensure media relationship is loaded
    if ($record && $record->exists) {
        $record->load('media');
    }
    
    $mediaItems = $record?->getMedia('buyer_po') ?? collect();
@endphp

@if($mediaItems->isNotEmpty())
    <div class="space-y-2">
        @foreach($mediaItems as $media)
            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                <div class="flex items-center space-x-2">
                    <a 
                        href="{{ route('buyer-quotes.po.download', ['buyerQuote' => $record->id, 'media' => $media->id]) }}"
                        target="_blank"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                    >
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        Open
                    </a>
                    <button 
                        type="button"
                        onclick="
                            if (confirm('Are you sure you want to delete this file?')) {
                                fetch('{{ route('buyer-quotes.po.delete', ['buyerQuote' => $record->id, 'media' => $media->id]) }}', {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                })
                                .then(async response => {
                                    const data = await response.json().catch(() => ({}));
                                    if (response.ok && data.success) {
                                        window.location.reload();
                                    } else {
                                        alert(data.message || 'Failed to delete file. Status: ' + response.status);
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('An error occurred while deleting the file: ' + error.message);
                                });
                            }
                        "
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                    >
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Delete
                    </button>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">No files uploaded yet.</p>
@endif
