<div class="h-screen flex bg-base-200">
    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col">
        <!-- Chat Header -->
        <header class="bg-base-100 shadow-md p-4">
            <h1 class="text-2xl font-bold text-primary">{{ $currentChatTitle }}</h1>
        </header>

        <!-- Chat Messages -->
        <div class="flex-1 overflow-hidden flex flex-col">
            <div class="flex-1 overflow-y-auto p-4 space-y-4" id="chat-messages">
                @foreach ($messages as $message)
                    <div class="chat {{ $message['sender'] === 'user' ? 'chat-end' : 'chat-start' }}">
                        <div class="chat-image avatar">
                            <div class="w-10 rounded-full">
                                <img src="{{ $message['sender'] === 'user' ? asset('images/user-avatar.png') : asset('images/ai-avatar.png') }}" alt="{{ $message['sender'] }} avatar" />
                            </div>
                        </div>
                        <div class="chat-header">
                            {{ $message['sender'] === 'user' ? 'You' : 'AI Assistant' }}
                        </div>
                        <div class="chat-bubble {{ $message['sender'] === 'user' ? 'chat-bubble-primary' : 'chat-bubble-secondary' }}">
                            {{ $message['content'] }}
                        </div>
                        <div class="chat-footer opacity-50">
                            {{ \Carbon\Carbon::parse($message['created_at'])->format('h:i A') }}
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Message Input -->
            <div class="p-4 bg-base-100 border-t border-base-300">
                <form wire:submit.prevent="sendMessage" class="flex space-x-2">
                    <input type="text" wire:model.defer="userMessage" placeholder="Type your message here..." class="flex-1 input input-bordered focus:input-primary" :disabled="$isLoading" />
                    <button type="submit" class="btn btn-primary" :disabled="$isLoading">
                        <span wire:loading.remove>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                            </svg>
                            Send
                        </span>
                        <span wire:loading>
                            <svg class="animate-spin h-5 w-5 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Sending...
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
                    <li>
                        <a href="#" wire:click.prevent="selectChat({{ $chat->id }})" 
                           class="block p-3 rounded-lg transition-colors duration-200 ease-in-out
                                  {{ $currentChatId === $chat->id 
                                     ? 'bg-primary text-primary-content' 
                                     : 'hover:bg-base-200' }}">
                            <div class="font-medium">{{ $chat->title }}</div>
                            <div class="text-sm opacity-70">
                                {{ \Carbon\Carbon::parse($chat->updated_at)->format('M d, Y') }}
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="p-4 border-t border-base-300">
            <button wire:click="createNewChat" class="btn btn-secondary w-full">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                New Chat
            </button>
        </div>
    </div>
</div>