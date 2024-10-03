<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class Room extends Component
{
    public $messages = [];
    public $userMessage = '';
    public $chats = [];
    public $currentChatId = null;
    public $currentChatTitle = '';

    public function mount()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $this->loadChats();
        if ($this->chats->isEmpty()) {
            $this->createNewChat();
        } else {
            $this->selectChat($this->chats->first()->id);
        }
    }

    public function loadChats()
    {
        $this->chats = Chat::where('user_id', Auth::id())->orderBy('created_at', 'desc')->get();
    }

    public function selectChat($chatId)
    {
        $chat = Chat::where('user_id', Auth::id())->findOrFail($chatId);
        $this->currentChatId = $chat->id;
        $this->currentChatTitle = $chat->title;
        $this->messages = $chat->messages()
            ->orderBy('created_at')
            ->get()
            ->map(function ($message) {
                return [
                    'sender' => $message->sender,
                    'content' => $message->text,
                ];
            })
            ->toArray();
    }

    public function createNewChat()
    {
        $title = 'New Chat ' . (Chat::where('user_id', Auth::id())->count() + 1);
        $chat = Chat::create([
            'title' => $title,
            'slug' => Str::slug($title),
            'user_id' => Auth::id(),
        ]);
        $this->loadChats();
        $this->selectChat($chat->id);
    }

    public function sendMessage()
    {
        if (empty($this->userMessage) || !$this->currentChatId) {
            return;
        }

        $message = Message::create([
            'chat_id' => $this->currentChatId,
            'text' => $this->userMessage,
            'sender' => 'user',
            'slug' => Str::slug($this->userMessage),
        ]);

        $this->messages[] = ['sender' => 'user', 'content' => $this->userMessage];

        // Simulate AI response
        $aiResponse = $this->getAIResponse($this->userMessage);
        $this->messages[] = ['sender' => 'agent', 'content' => $aiResponse];

        Message::create([
            'chat_id' => $this->currentChatId,
            'text' => $aiResponse,
            'sender' => 'agent',
            'slug' => Str::slug($aiResponse),
        ]);

        $this->userMessage = '';
        $this->dispatch('messageAdded');
    }

    private function getAIResponse($userMessage)
    {
        // Simulate AI response (replace this with actual AI integration later)
        $responses = [
            "That's an interesting point. Can you elaborate?",
            "I understand. Here's what I think about that...",
            "Thank you for sharing. Have you considered this perspective?",
            "That's a great question. Let me explain...",
            "I see where you're coming from. Here's another way to look at it:",
        ];
        return $responses[array_rand($responses)] . " (In response to: '$userMessage')";
    }

    public function render()
    {
        return view('livewire.room')->layout('layouts.app');
    }
}
