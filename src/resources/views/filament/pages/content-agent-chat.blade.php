<x-filament-panels::page>
    <div
        class="flex flex-col gap-4"
        @if ($this->isWaiting) wire:poll.3s @endif
    >
        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            @forelse ($this->messages as $message)
                <div class="flex {{ $message->role === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div
                        @class([
                            'max-w-2xl rounded-lg px-3 py-2 text-sm whitespace-pre-wrap',
                            'bg-primary-600 text-white' => $message->role === 'user',
                            'bg-gray-100 dark:bg-gray-800' => $message->role === 'assistant',
                        ])
                    >
                        @if ($message->attachment_name)
                            <div class="mb-1 text-xs opacity-75">
                                📎 {{ $message->attachment_name }}
                            </div>
                        @endif

                        {{ $message->content }}
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">
                    Write what kind of lesson you want, optionally attach a PDF with words, phrases or grammar to
                    teach, and the agent will draft it for you to review.
                </p>
            @endforelse

            @if ($this->isWaiting)
                <div class="flex justify-start">
                    <div class="rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-500 dark:bg-gray-800">
                        Thinking…
                    </div>
                </div>
            @endif
        </div>

        <form wire:submit="send" class="flex flex-col gap-3">
            {{ $this->form }}

            <div>
                <x-filament::button type="submit" :disabled="$this->isWaiting">
                    Send
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
