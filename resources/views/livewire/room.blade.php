<div class="bg-base-200 max-h-screen min-h-screen">

    @section('title', 'Dashboard')
    @include('navigation-menu')

<div class="flex bg-base-200 h-[calc(100vh-5em)]">

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col">
        <!-- Chat Header -->
        <header class="bg-base-100 shadow-md p-4">
            <h1 class="text-2xl font-bold text-primary" wire:poll.5s="$refresh">{{ $currentChatTitle }}</h1>
        </header>

        <!-- Chat Messages -->
        <div class="flex-1 overflow-hidden flex flex-col">
            <div class="flex-1 overflow-y-auto p-4 space-y-4" id="chat-messages">
                @foreach ($messages as $message)
                    <div class="chat {{ $message['sender'] === 'user' ? 'chat-end' : 'chat-start' }}" wire:key="{{ $message['id'] }}">
                        <div class="chat-image avatar">
                            <div class="w-10 rounded-full">
                                    <span class="w-10 h-10 rounded-full border-2 border-primary bg-base-100 text-white flex items-center justify-center">
                                        {{ $message['sender'] === 'user' ? strtoupper(substr(Auth::user()->name, 0, 1)) : 'A' }}
                                    </span>
                            </div>
                        </div>
                        <div class="chat-header pb-1 px-1">
                            {{ $message['sender'] === 'user' ? 'You' : 'AI Assistant' }}
                        </div>
                        <div class="chat-bubble {{ $message['sender'] === 'user' ? 'chat-bubble-primary' : 'chat-bubble bg-neutral-900 p-0 rounded-3xl' }}">
                            @if ($message['sender'] === 'user')
                                {{ $message['content'] }}
                            @else
                                <div class="prose max-w-none text-white">
                                    <div wire:key="{{ $message['id'] }}" class="join join-vertical w-full border-0">
                                        <div class="collapse collapse-arrow join-item border-b border-base-300">
                                            <input type="radio" name="accordion-{{ $message['id'] }}" /> 
                                            <div class="collapse-title text-xl font-medium">
                                                Initial Output
                                            </div>
                                            <div class="collapse-content"> 
                                                <p>{!! Str::markdown($message['initial_response'] ?? '') !!}</p>
                                            </div>
                                        </div>
                                        <div class="collapse collapse-arrow join-item border-t border-b border-base-300">
                                            <input type="radio" name="accordion-{{ $message['id'] }}" /> 
                                            <div class="collapse-title text-xl font-medium">
                                                Verified Response
                                            </div>
                                            <div class="collapse-content"> 
                                                <p>{!! Str::markdown($message['verified_response'] ?? '') !!}</p>
                                            </div>
                                        </div>
                                        <div class="collapse collapse-arrow join-item border-t border-b border-base-300">
                                            <input type="radio" name="accordion-{{ $message['id'] }}" /> 
                                            <div class="collapse-title text-xl font-medium">
                                                Search Results
                                            </div>
                                            <div class="collapse-content"> 
                                                <div class="monospace text-xs">{{ strip_tags($message['search_response'] ?? '') }}</div>
                                            </div>
                                        </div>
                                        <div class="collapse collapse-arrow join-item border-t border-b border-base-300">
                                            <input type="radio" name="accordion-{{ $message['id'] }}" /> 
                                            <div class="collapse-title text-xl font-medium">
                                                Validated Reasoning
                                            </div>
                                            <div class="collapse-content"> 
                                                <p>{!! Str::markdown($message['validated_reasoning'] ?? '') !!}</p>
                                            </div>
                                        </div>
                                        <div class="collapse collapse-arrow join-item border-t border-base-300">
                                            <input type="radio" name="accordion-{{ $message['id'] }}" checked /> 
                                            <div class="collapse-title text-xl font-medium">
                                                Final Output
                                            </div>
                                            <div class="collapse-content"> 
                                                <p>{!! Str::markdown($message['final_response'] ?? '') !!}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="chat-footer opacity-50 py-1 px-1">
                            {{ \Carbon\Carbon::parse($message['created_at'])->format('h:i A') }}
                        </div>
                    </div>
                @endforeach
                @if ($placeholderMessage)
                    <div class="chat chat-start">
                        <div class="chat-image avatar">
                            <div class="w-10 rounded-full">
                                <img src="{{ asset('images/ai-avatar.png') }}" alt="AI Assistant avatar" />
                            </div>
                        </div>
                        <div class="chat-header">
                            AI Assistant
                        </div>
                        <div class="chat-bubble chat-bubble-secondary">
                            <div class="flex items-center">
                                <span class="mr-2" id="ai-thinking-message">{{ $placeholderMessage['content'] }}</span>
                                <span class="loading loading-dots loading-sm"></span>
                            </div>
                        </div>
                        <div class="chat-footer opacity-50">
                            {{ \Carbon\Carbon::parse($placeholderMessage['created_at'])->format('h:i A') }}
                        </div>
                    </div>
                @endif
            </div>

            <!-- Message Input -->
            <div class="p-4 bg-base-100 border-t border-base-300">
                <form wire:submit.prevent="sendMessage" class="flex space-x-2">
                    <input type="text" wire:model.defer="userMessage" placeholder="Type your message here..." class="flex-1 input input-bordered focus:input-primary" :disabled="$isLoading" />
                    <button type="submit" class="btn btn-primary" :disabled="$isLoading">
                        <span wire:loading.remove wire:target="sendMessage">
                            <span class="flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                            </svg>&nbsp;
                            Send
                            </span>
                        </span>
                        <span wire:loading wire:target="sendMessage">
                            <span class="flex items-center justify-center">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>&nbsp;
                            Sending...
                            </span>
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Sidebar for Chat Selection -->
    <div class="w-80 bg-base-100 border-l border-base-300 overflow-y-auto flex flex-col">
        <div class="p-4 flex-1">
            <h2 class="text-xl font-semibold mb-4 text-primary">Chats</h2>
            <ul class="space-y-2">
                @foreach ($chats as $chat)
                    <li wire:key="{{ $chat->id }}">
                        <div class="flex items-center justify-between p-3 rounded-lg transition-colors duration-200 ease-in-out
                                  {{ $currentChatId === $chat->id 
                                     ? 'bg-primary text-primary-content' 
                                     : 'hover:bg-base-200' }}">
                            <a href="#" wire:click.prevent="selectChat({{ $chat->id }})" 
                               class="flex-grow">
                                <div class="font-medium">{{ $chat->title }}</div>
                                <div class="text-sm opacity-70">
                                    {{ \Carbon\Carbon::parse($chat->updated_at)->format('M d, Y') }}
                                </div>
                            </a>
                            <div>
                                <button wire:click="deleteChat({{ $chat->id }})" 
                                        class="btn btn-ghost btn-sm"
                                        onclick="confirm('Are you sure you want to delete this chat?') || event.stopImmediatePropagation()"
                                        wire:loading.remove wire:target="selectChat({{ $chat->id }})">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <div wire:loading wire:target="selectChat({{ $chat->id }})" class="btn btn-ghost btn-sm">
                                    <svg class="animate-spin h-5 w-5 mt-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="p-4 border-t border-base-300 sticky bottom-0 z-10 bg-base-100">
            <button wire:click="createNewChat" class="btn btn-ghost w-full">

                    <svg wire:loading.remove wire:target="createNewChat" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    <svg wire:loading wire:target="createNewChat" class="animate-spin h-5 w-5 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    New Chat
            </button>
        </div>
    </div>
</div>
</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        const chatMessages = document.getElementById('chat-messages');
        const scrollToBottom = () => {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        };

        // Scroll to bottom on initial load
        scrollToBottom();

        // Scroll to bottom when new messages are added
        Livewire.on('messageAdded', scrollToBottom);

        // Update AI thinking message
        Livewire.on('aiThinking', ({ message }) => {
            const aiThinkingMessage = document.getElementById('ai-thinking-message');
            if (aiThinkingMessage) {
                aiThinkingMessage.textContent = message;
            }
        });

        // Scroll to bottom when the thinking message appears or disappears
        new MutationObserver(scrollToBottom).observe(chatMessages, { childList: true, subtree: true });

        // Add event listener for accordion toggles
        document.addEventListener('change', function(e) {
            if (e.target && e.target.type === 'checkbox' && e.target.name.startsWith('accordion-')) {
                setTimeout(() => {
                    scrollToBottom();
                }, 100); // Delay to ensure content has expanded
            }
        });

        // Add event listener for chat deletion
        Livewire.on('chatDeleted', () => {
            // You can add any additional UI updates here if needed
            console.log('Chat deleted successfully');
        });
    });
</script>