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
    private $systemPrompt = "You are an AI assistant named AgentOps, an AI-powered agent operations platform. You will never say your actualy model name and only refer to yourself as AgentOps, this is imperative. You will be provided with a question or set of instructions to follow. You can search the internet with [[search query]] and you will be provided with search results. You will then provide a response in accordance with the instructions. ";
    private $converter;
    private $maxRetries = 3;
    private $maxContextLength = 8000; // Adjust this based on Groq's limits
    public $placeholderMessage = null;
    public $isFirstMessage = true;

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
                    'search_response' => $message->{'3'},
                    'validated_reasoning' => $message->{'4'},
                    'final_response' => $message->{'5'},
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
        
        if ($this->isFirstMessage) {
            $this->updateChatTitle();
        }
        
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
                $userPrompt = end($this->messages)['content'];

                $this->dispatch('aiThinking', message: "Generating initial response...");
                $this->dispatch('$refresh');
                usleep(100000); // 0.1 second delay
                $initialResponse = $this->getAIResponse($contextLength, $userPrompt, true); // Include chat history

                $this->dispatch('aiThinking', message: "Verifying response...");
                $this->dispatch('$refresh');
                usleep(100000); // 0.1 second delay
                $verifiedResponse = $this->verifyAIResponse($userPrompt, $initialResponse['raw'], $contextLength);

                $this->dispatch('aiThinking', message: "Searching for additional information...");
                $this->dispatch('$refresh');
                usleep(100000); // 0.1 second delay
                $searchResponse = $this->performSearch($verifiedResponse['raw']);

                $this->dispatch('aiThinking', message: "Validating reasoning...");
                $this->dispatch('$refresh');
                usleep(100000); // 0.1 second delay
                $validatedReasoning = $this->validateReasoning($userPrompt, $initialResponse['raw'], $verifiedResponse['raw'], $searchResponse, $contextLength);

                $this->dispatch('aiThinking', message: "Finalizing response with validated reasoning...");
                $this->dispatch('$refresh');
                usleep(100000); // 0.1 second delay
                $finalResponse = $this->getFinalAIResponse($userPrompt, $initialResponse['raw'], $verifiedResponse['raw'], $searchResponse, $validatedReasoning['raw'], $contextLength);

                $this->processAIResponse($initialResponse['raw'], $verifiedResponse['raw'], $searchResponse, $validatedReasoning['raw'], $finalResponse['raw']);
                return;
            } catch (\Exception $e) {
                if ($e->getCode() >= 400 && $e->getCode() <= 499 && $retries < $this->maxRetries - 1) {
                    $retries++;
                    $contextLength = (int)($contextLength * 0.8); // Reduce context length by 20%
                    $this->dispatch('aiThinking', message: "Retrying with shorter context... (Attempt {$retries})");
                } else {
                    $this->processAIResponse('', '', '', '', 'Sorry, there was an error processing your request: ' . $e->getMessage() . ' code: ' . $e->getCode());
                    return;
                }
            }
        }
    }

    private function performSearch($initialResponse)
    {
        preg_match('/\[\[(.*?)\]\]/', $initialResponse, $matches);
        
        if (empty($matches)) {
            return "No search necessary";
        }

        $searchQuery = str_replace('%20', '+', urlencode($matches[1]));
        $response = Http::withHeaders([
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/png,image/svg+xml,*/*;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Connection' => 'keep-alive',
            'Content-Length' => '27',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Host' => 'html.duckduckgo.com',
            'Origin' => 'https://html.duckduckgo.com',
            'Priority' => 'u=0, i',
            'Referer' => 'https://html.duckduckgo.com/',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-User' => '?1',
            'TE' => 'trailers',
            'Upgrade-Insecure-Requests' => '1',
            'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0',
        ])->asForm()->post('https://html.duckduckgo.com/html', [
            'q' => $searchQuery,
            'b' => '',
            'kl' => '',
            'df' => '',
        ]);

        if ($response->successful()) {
            $html = $response->body();
            $strippedHtml = preg_replace('/<head>.*?<\/head>/s', '', $html);
            $strippedHtml = strip_tags($strippedHtml);
            return "Search results for query '$searchQuery':\n\n" . $strippedHtml;
        } else {
            return "Search failed: " . $response->status();
        }
    }

    private function verifyAIResponse($userPrompt, $initialResponse, $contextLength)
    {
        $verificationPrompt = "User's initial prompt: $userPrompt\n\nYou wrote the following initial response:\n\n---\n\n$initialResponse\n\n---\n\nPlease carefully analyze this response and provide a detailed verification, make sure it is absolutely true and correct. Provide your verification analysis and explanation, pointing out any inconsistencies of logic or factual errors. You MUST search for information by adding a search query in double brackets like this: [[search query]] - Do not be generic when searching and get to the root of the initial user's prompt to provide the most accurate response.";
        
        return $this->getAIResponse($contextLength, $verificationPrompt, false); // Exclude chat history
    }

    private function validateReasoning($userPrompt, $initialResponse, $verifiedResponse, $searchResponse, $contextLength)
    {
        $validationPrompt = "User's initial prompt: $userPrompt\n\nYou are a critical thinking expert. You will be presented with an initial response, a verification of that response, and search results. Your task is to thoroughly evaluate the reasoning process and the quality of the **verification**.\n\nInitial response:\n\n---\n\n$initialResponse\n\n---\n\nVerification:\n\n---\n\n$verifiedResponse\n\n---\n\nSearch results:\n\n---\n\n$searchResponse\n\n---\n\nPlease ensure the quality of the reasoning and verification process. Your goal is to ensure the highest quality of reasoning and response. Don't over complicate things but still be detailed.";
        
        return $this->getAIResponse($contextLength, $validationPrompt, false); // Exclude chat history
    }

    private function getFinalAIResponse($userPrompt, $initialResponse, $verifiedResponse, $searchResponse, $validatedReasoning, $contextLength)
    {
        $finalPrompt = "User's initial prompt: $userPrompt\n\nYou are tasked with providing the final, most accurate and comprehensive response. You have been through a rigorous process of initial response, verification, search, and reasoning validation. Here are the steps:\n\nInitial response:\n\n---\n\n$initialResponse\n\n---\n\nVerified response:\n\n---\n\n$verifiedResponse\n\n---\n\nSearch results:\n\n---\n\n$searchResponse\n\n---\n\nValidated reasoning:\n\n---\n\n$validatedReasoning\n\n---\n\nYour task:\nProvide a final, authoritative response that represents the most accurate, complete, and well-reasoned answer to the user's initial prompt\n\nEnsure your final response is clear, concise, and directly addresses the original query or task.";
        
        return $this->getAIResponse($contextLength, $finalPrompt, false); // Exclude chat history
    }

    private function processAIResponse($initialResponse, $verifiedResponse, $searchResponse, $validatedReasoning, $finalResponse)
    {
        $this->placeholderMessage = null;

        $message = Message::create([
            'chat_id' => $this->currentChatId,
            'text' => $finalResponse,
            'sender' => 'agent',
            'slug' => '',
            '1' => $initialResponse,
            '2' => $verifiedResponse,
            '3' => $searchResponse,
            '4' => $validatedReasoning,
            '5' => $finalResponse,
        ]);

        $this->messages[] = [
            'sender' => 'agent',
            'content' => $finalResponse,
            'initial_response' => $initialResponse,
            'verified_response' => $verifiedResponse,
            'search_response' => $searchResponse,
            'validated_reasoning' => $validatedReasoning,
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

    private function getAIResponse($contextLength, $additionalPrompt = '', $includeChatHistory = false)
    {
        $apiKey = env('GROQ_KEY');
        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $messages = [['role' => 'system', 'content' => $this->systemPrompt]];

        if ($includeChatHistory) {
            $chatHistory = $this->getChatHistory($contextLength);
            $messages = array_merge($messages, $chatHistory);
        }

        if ($additionalPrompt) {
            $messages[] = ['role' => 'user', 'content' => $additionalPrompt];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'model' => 'gemma-7b-it',
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

    public function deleteChat($chatId)
    {
        $chat = Chat::where('user_id', Auth::id())->findOrFail($chatId);
        $chat->delete();

        $this->loadChats();

        if ($this->currentChatId == $chatId) {
            if ($this->chats->isNotEmpty()) {
                $this->selectChat($this->chats->first()->id);
            } else {
                $this->createNewChat();
            }
        }

        $this->dispatch('chatDeleted');
    }

    private function updateChatTitle()
    {
        $apiKey = env('GROQ_KEY');
        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'model' => 'gemma-7b-it',
            'messages' => [
                ['role' => 'system', 'content' => 'Generate a very short 3-5 word title to describe the following question. Only reply with 3-5 words.'],
                ['role' => 'user', 'content' => $this->userMessage],
            ],
            'max_tokens' => 10, // Limit the response to ensure a short title
        ]);

        if ($response->successful()) {
            $responseData = $response->json();
            $newTitle = $responseData['choices'][0]['message']['content'] ?? 'New Chat';
            
            // Trim and limit the title to 5 words
            $newTitle = implode(' ', array_slice(explode(' ', trim($newTitle)), 0, 5));
            
            $chat = Chat::find($this->currentChatId);
            $chat->update(['title' => $newTitle]);
            
            $this->currentChatTitle = $newTitle;
            $this->isFirstMessage = false;
            
            usleep(100000); // 0.1 second delay
            $this->loadChats(); // Refresh the chat list
        }
    }
}