<div class="h-screen flex bg-gray-100">
    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col">
        <div class="flex-1 overflow-hidden flex flex-col">
            <div class="flex-1 overflow-y-auto p-4 space-y-4" id="chat-messages">
                @foreach ($messages as $message)
                    <div class="chat {{ $message['sender'] === 'user' ? 'chat-end' : 'chat-start' }}">
                        <div class="chat-bubble {{ $message['sender'] === 'user' ? 'chat-bubble-primary' : 'chat-bubble-secondary' }}">
                            {{ $message['content'] }}
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="p-4 bg-white border-t">
                <form wire:submit.prevent="sendMessage" class="flex space-x-2">
                    <input type="text" wire:model.defer="userMessage" placeholder="Type your message here..." class="flex-1 input input-bordered" />
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove>Send</span>
                        <span wire:loading>Sending...</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Sidebar for Chat Selection -->
    <div class="w-64 bg-white border-l border-gray-200 overflow-y-auto flex flex-col">
        <div class="p-4 flex-1">
            <h2 class="text-lg font-semibold mb-4">Chats</h2>
            <ul class="space-y-2">
                @foreach ($chats as $chat)
                    <li>
                        <a href="#" wire:click.prevent="selectChat({{ $chat->id }})" class="block p-2 rounded hover:bg-gray-100 {{ $currentChatId === $chat->id ? 'bg-gray-200' : '' }}">
                            {{ $chat->title }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="p-4 border-t">
            <button wire:click="createNewChat" class="btn btn-secondary w-full">
                New Chat
            </button>
        </div>
    </div>
</div>
