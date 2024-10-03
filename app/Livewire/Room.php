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
    private $systemPrompt = "You are an AI assistant named AgentOps, an AI-powered agent operations platform. You will never say your actualy model name and only refer to yourself as AgentOps, this is imperative. You will be provided with a question or set of instructions to follow. You will then provide a response with the reasoning for your actions. ";
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
                    'id' => $message->id,
                    'initial_response' => $message->{'1'},
                    'verified_response' => $message->{'2'},
                    'final_response' => $message->{'3'},
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
        
        $this->messages[] = ['sender' => 'user', 'content' => $this->userMessage, 'created_at' => $message->created_at, 'id' => $message->id];
        $this->userMessage = '';

        // Add placeholder message
        $this->placeholderMessage = [
            'sender' => 'agent',
            'content' => 'Thinking...',
            'created_at' => now(),
        ];

        $this->dispatch('messageAdded');

        // Get AI response asynchronously
        $this->getAIResponseWithRetry();
    }

    private function getAIResponseWithRetry()
    {
        $retries = 0;
        $contextLength = $this->maxContextLength;

        while ($retries < $this->maxRetries) {
            try {
                $this->dispatch('aiThinking', message: "Generating initial response...");
                $initialResponse = $this->getAIResponse($contextLength);

                $this->dispatch('aiThinking', message: "Verifying response...");
                $verifiedResponse = $this->verifyAIResponse($initialResponse['raw'], $contextLength);

                $this->dispatch('aiThinking', message: "Finalizing response with reasoning...");
                $finalResponse = $this->getFinalAIResponse($initialResponse['raw'], $verifiedResponse['raw'], $contextLength);

                $this->processAIResponse($initialResponse['raw'], $verifiedResponse['raw'], $finalResponse['raw']);
                return;
            } catch (\Exception $e) {
                if ($e->getCode() == 429 && $retries < $this->maxRetries - 1) {
                    $retries++;
                    $contextLength = (int)($contextLength * 0.8); // Reduce context length by 20%
                    $this->dispatch('aiThinking', message: "Retrying with shorter context... (Attempt {$retries})");
                } else {
                    $this->processAIResponse([
                        'raw' => 'Sorry, there was an error processing your request: ' . $e->getMessage(),
                        'html' => '<p>Sorry, there was an error processing your request: ' . $e->getMessage() . '</p>',
                    ]);
                    return;
                }
            }
        }
    }

    private function verifyAIResponse($initialResponse, $contextLength)
    {
        $verificationPrompt = "Please verify the correctness of the following response and provide a brief explanation of your reasoning and what should be changed:\n\n" . $initialResponse;
        
        return $this->getAIResponse($contextLength, $verificationPrompt);
    }

    private function getFinalAIResponse($initialResponse, $verifiedResponse, $contextLength)
    {
        $finalPrompt = "Given the initial response:\n\n---\n\n$initialResponse\n\n---\n\nAnd the verified reasoning:\n\n---\n\n$verifiedResponse\n\n---\n\nPlease provide a final draft of the initial response, ensuring its correctness according to the verified reasoning. At the end, briefly explain your reasoning for the final answer.";
        
        return $this->getAIResponse($contextLength, $finalPrompt);
    }

    private function processAIResponse($initialResponse, $verifiedResponse, $finalResponse)
    {
        $this->placeholderMessage = null;

        $message = Message::create([
            'chat_id' => $this->currentChatId,
            'text' => $finalResponse,
            'sender' => 'agent',
            'slug' => '',
            '1' => $initialResponse,
            '2' => $verifiedResponse,
            '3' => $finalResponse,
        ]);

        $this->messages[] = [
            'sender' => 'agent',
            'content' => $finalResponse,
            'initial_response' => $initialResponse,
            'verified_response' => $verifiedResponse,
            'final_response' => $finalResponse,
            'created_at' => now(),
            'id' => $message->id,
        ];

        $this->isLoading = false;
        $this->dispatch('messageAdded');
    }

    #[On('messageAdded')]
    public function scrollToBottom()
    {
        $this->dispatch('scrollChat');
    }

    private function getAIResponse($contextLength, $additionalPrompt = '')
    {
        $apiKey = env('GROQ_KEY');
        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $chatHistory = $this->getChatHistory($contextLength);

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt]],
            $chatHistory
        );

        if ($additionalPrompt) {
            $messages[] = ['role' => 'user', 'content' => $additionalPrompt];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'model' => 'llama-3.2-90b-text-preview',
            'messages' => $messages,
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
                           ->orderBy('id', 'desc')
                           ->get()->reverse();

            // Start of Selection
            foreach ($messages as $message) {
                $messageContent = $message->text;
                $messageLength = strlen($messageContent);

                $history[] = [
                    'role' => $message->sender === 'user' ? 'user' : 'assistant',
                    'content' => $messageContent
                ];

                $currentLength += $messageLength;

                while ($currentLength > $contextLength && count($history) > 0) {
                    $removedMessage = array_shift($history);
                    $currentLength -= strlen($removedMessage['content']);
                }
            }

        return $history;
    }

    private function parseMarkdown($text)
    {
        $converter = new CommonMarkConverter(['html_input' => 'allow', 'allow_unsafe_links' => false]);
        return $converter->convert($text)->getContent();
    }

    public function render()
    {
        return view('livewire.room')->layout('layouts.app');
    }
}