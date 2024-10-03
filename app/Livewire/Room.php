<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use League\CommonMark\CommonMarkConverter;
use Livewire\Attributes\On;

class Room extends Component
{
    public $messages = [];
    public $userMessage = '';
    public $chats = [];
    public $currentChatId = null;
    public $currentChatTitle = '';
    public $isLoading = false;
    public $isThinking = false;
    public $thinkingMessage = '';
    private $chatHistory = [];
    private $systemPrompt = "You are an AI assistant for AgentOps, an AI-powered agent operations platform. Provide helpful and concise responses to user queries, and be ready to perform tasks like web-scraping and reasoning when requested.";
    private $converter;
    private $maxRetries = 3;
    private $maxContextLength = 8000; // Adjust this based on Groq's limits
    public $placeholderMessage = null;

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
        $this->isThinking = true;
        $this->thinkingMessage = 'Thinking...';

        $message = Message::create([
            'chat_id' => $this->currentChatId,
            'text' => $this->userMessage,
            'sender' => 'user',
            'slug' => '',
        ]);
        
        $this->messages[] = ['sender' => 'user', 'content' => $this->userMessage, 'created_at' => $message->created_at];
        $this->userMessage = '';

        // Add placeholder message
        $this->placeholderMessage = [
            'sender' => 'agent',
            'content' => 'Thinking...',
            'created_at' => now(),
        ];

        $this->dispatch('messageAdded');

        // Get AI response
        $this->getAIResponseWithRetry();
    }

    private function getAIResponseWithRetry()
    {
        $retries = 0;
        $contextLength = $this->maxContextLength;

        while ($retries < $this->maxRetries) {
            try {
                $aiResponse = $this->getAIResponse($contextLength);
                $this->processAIResponse($aiResponse);
                return;
            } catch (\Exception $e) {
                if ($e->getCode() == 429 && $retries < $this->maxRetries - 1) {
                    $retries++;
                    $contextLength = (int)($contextLength * 0.8); // Reduce context length by 20%
                    $this->thinkingMessage = "Retrying with shorter context... (Attempt {$retries})";
                } else {
                    $this->processAIResponse([
                        'raw' => 'Sorry, there was an error processing your request.',
                        'html' => '<p>Sorry, there was an error processing your request.</p>',
                    ]);
                    return;
                }
            }
        }
    }

    private function processAIResponse($aiResponse)
    {
        $this->isThinking = false;
        $this->thinkingMessage = '';

        // Remove placeholder message
        $this->placeholderMessage = null;

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

        $this->isLoading = false;
        $this->dispatch('messageAdded');
    }

    #[On('messageAdded')]
    public function scrollToBottom()
    {
        $this->dispatch('scrollChat');
    }

    private function getAIResponse($contextLength)
    {
        $apiKey = env('GROQ_KEY');
        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $chatHistory = $this->getChatHistory($contextLength);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'model' => 'llama-3.2-90b-text-preview',
            'messages' => array_merge(
                [['role' => 'system', 'content' => $this->systemPrompt]],
                $chatHistory
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
            throw new \Exception('API request failed', $response->status());
        }
    }

    private function getChatHistory($contextLength)
    {
        $history = [];
        $currentLength = 0;
        $messages = Message::where('chat_id', $this->currentChatId)
                           ->orderBy('created_at', 'desc')
                           ->get()
                           ->reverse();

        foreach ($messages as $message) {
            $messageContent = $message->text;
            $messageLength = strlen($messageContent);

            if ($currentLength + $messageLength > $contextLength) {
                break;
            }

            $history[] = [
                'role' => $message->sender === 'user' ? 'user' : 'assistant',
                'content' => $messageContent
            ];

            $currentLength += $messageLength;
        }

        return $history;
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
