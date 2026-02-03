<div class="space-y-6">
    @foreach($types as $typeKey => $typeLabel)
        @php
            $typeTemplates = $templates->get($typeKey, collect());
        @endphp
        
        @if($typeTemplates->isNotEmpty())
            <div>
                <h3 class="text-lg font-semibold mb-3">{{ $typeLabel }}</h3>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($typeTemplates as $template)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $template->name }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $typeLabel }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @if($template->is_default)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                Default
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                Custom
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $template->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                        @if(!$template->is_default)
                                            <button 
                                                wire:click="mountAction('editTemplate', { id: {{ $template->id }} })"
                                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3"
                                            >
                                                Edit
                                            </button>
                                            <button 
                                                wire:click="deleteTemplate({{ $template->id }})"
                                                onclick="return confirm('Are you sure you want to delete this template? If it is currently selected, the default template will be used instead.')"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                Delete
                                            </button>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-600">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endforeach
    
    @if($templates->isEmpty())
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <p>No custom templates created yet. Use the "+" button next to template selects to create new templates.</p>
        </div>
    @endif
</div>
