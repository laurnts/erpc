@php
    $actorType = $this->actorType();
@endphp

<div
    class="mt-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5"
    wire:key="note-composer-{{ $request->getKey() }}"
>
    <form wire:submit="submit" class="space-y-3">
        <div class="flex items-center gap-2">
            <span class="text-{{ $actorType->getColor() }}-600 dark:text-{{ $actorType->getColor() }}-400">
                @svg($actorType->getIcon(), 'h-5 w-5')
            </span>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                Add a note
            </span>
        </div>

        <textarea
            wire:model="body"
            rows="3"
            placeholder="Write a note for this request…"
            class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm leading-relaxed text-gray-950 shadow-sm placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-white/15 dark:bg-white/10 dark:text-white dark:placeholder:text-gray-500"
        ></textarea>
        @error('body')
            <p class="text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror

        @if ($this->canChooseVisibility())
            <div class="flex flex-wrap items-center gap-3">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Visibility</label>
                <select
                    wire:model.live="visibility"
                    class="rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                >
                    <option value="{{ \App\Enums\NoteVisibility::Internal->value }}">Internal only</option>
                    <option value="{{ \App\Enums\NoteVisibility::Buyer->value }}">Share with buyer</option>
                    <option value="{{ \App\Enums\NoteVisibility::Supplier->value }}">Share with supplier</option>
                </select>

                @if ($visibility === \App\Enums\NoteVisibility::Supplier->value)
                    <select
                        wire:model="supplierCompanyId"
                        class="rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    >
                        <option value="">Choose supplier…</option>
                        @foreach ($this->supplierOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            @error('supplierCompanyId')
                <p class="text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror
        @endif

        <div class="flex flex-col gap-2">
            <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                @svg('heroicon-o-paper-clip', 'h-4 w-4')
                <span>Attach files</span>
                <input type="file" wire:model="attachments" multiple class="sr-only" />
            </label>

            <div wire:loading wire:target="attachments" class="text-xs text-gray-400 dark:text-gray-500">
                Uploading…
            </div>

            @if ($attachments !== [])
                <ul class="flex flex-wrap gap-2">
                    @foreach ($attachments as $file)
                        <li
                            class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300"
                            wire:key="attachment-{{ $loop->index }}"
                        >
                            @svg('heroicon-o-document', 'h-3.5 w-3.5')
                            {{ is_object($file) && method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : 'file' }}
                        </li>
                    @endforeach
                </ul>
            @endif
            @error('attachments.*')
                <p class="text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-end gap-2">
            <span wire:loading wire:target="submit" class="text-xs text-gray-400 dark:text-gray-500">Posting…</span>

            <x-filament::button
                type="submit"
                size="sm"
                icon="heroicon-o-paper-airplane"
                wire:loading.attr="disabled"
                wire:target="submit"
            >
                Post
            </x-filament::button>
        </div>
    </form>
</div>
