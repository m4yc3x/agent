<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use League\CommonMark\CommonMarkConverter;

class Room extends Component
{
    public $messages = [];
    public $userMessage = '';
    public $chats = [];
    public $currentChatId = null;
    public $currentChatTitle = '';
    public $isLoading = false;
    private $chatHistory = [];
    private $systemPrompt = "You are an AI assistant for AgentOps, an AI-powered agent operations platform. Provide helpful and concise responses to user queries, and be ready to perform tasks like web-scraping and reasoning when requested.";
    private $converter;

    public function boot()
    {
        $this->converter = new CommonMarkConverter();
    }

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
                    'html_content' => $message->sender === 'agent' ? $this->parseMarkdown($message->text) : null,
                    'created_at' => $message->created_at,
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

        $this->isLoading = true;

        $message = Message::create([
            'chat_id' => $this->currentChatId,
            'text' => $this->userMessage,
            'sender' => 'user',
            'slug' => '',
        ]);
        
        $this->dispatch('messageAdded');

        $this->messages[] = ['sender' => 'user', 'content' => $this->userMessage, 'created_at' => $message->created_at];

        // Prepare chat history for API request
        $this->chatHistory[] = ['role' => 'user', 'content' => $this->userMessage];

        // Get AI response
        $aiResponse = $this->getAIResponse();

        $this->messages[] = [
            'sender' => 'agent',
            'content' => $aiResponse['raw'],
            'html_content' => $aiResponse['html'],
            'created_at' => now()
        ];

        Message::create([
            'chat_id' => $this->currentChatId,
            'text' => $aiResponse['raw'],
            'sender' => 'agent',
            'slug' => '',
        ]);

        $this->chatHistory[] = ['role' => 'assistant', 'content' => $aiResponse['raw']];

        $this->userMessage = '';
        $this->isLoading = false;
        $this->dispatch('messageAdded');
    }

    private function getAIResponse()
    {
        $apiKey = env('GROQ_KEY');
        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'model' => 'llama3-70b-8192',
            'messages' => array_merge(
                [['role' => 'system', 'content' => $this->systemPrompt]],
                $this->chatHistory
            ),
        ]);

        if ($response->successful()) {
            $responseData = $response->json();
            $rawContent = $responseData['choices'][0]['message']['content'] ?? 'Sorry, I couldn\'t generate a response.';
            
            return [
                'raw' => $rawContent,
                'html' => $this->parseMarkdown($rawContent),
            ];
        } else {
            return [
                'raw' => 'Sorry, there was an error processing your request.',
                'html' => '<p>Sorry, there was an error processing your request.</p>',
            ];
        }
    }

    private function parseMarkdown($text)
    {
        return $this->converter->convert($text)->getContent();
    }

    public function render()
    {
        return view('livewire.room')->layout('layouts.app');
    }
}
