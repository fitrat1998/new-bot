<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SergiX44\Nutgram\Nutgram;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TelegramController extends Controller
{
    // Channel details for subscription checks
    protected $channels = [
        [
            'id' => '@barnomahoyi_tojiki',
            'link' => 'https://t.me/barnomahoyi_tojiki'
        ],
        [
            'id' => '@mirkomil_kuhistoniy_blog',
            'link' => 'https://t.me/mirkomil_kuhistoniy_blog'
        ]
    ];

    public function handlePolling()
    {
        $bot = new Nutgram(env('TELEGRAM_BOT_TOKEN'));

        while (true) {
            // Polling for updates from Telegram using Nutgram's correct method
            $updates = $bot->getUpdates(); // Get updates as objects

            foreach ($updates as $update) {
                // Check if the update contains a message
                if (isset($update->message)) {
                    $this->handleMessage($update, $bot);
                } elseif (isset($update->callback_query)) {
                    $this->handleCallbackQuery($update->callback_query, $bot);
                }
            }

            // Small delay to avoid hitting Telegram's rate limits
            sleep(2);  // Sleep for 2 seconds before next polling attempt
        }
    }

    // Handling the incoming messages
    protected function handleMessage($update, Nutgram $bot)
    {
        $message = $update->message;
        if (!$message || !$message->hasText()) {
            Log::warning('No message or empty message received.');
            return;
        }

        $text = $message->text();
        $chatId = $message->chat()->id();
        $userId = $message->from()->id();

        Log::info("User [$chatId] sent message: $text");

        if ($text == '/start') {
            $this->handleStartCommand($chatId, $userId, $bot);
        } else {
            $this->handleGeminiRequest($chatId, $text, $bot);
        }
    }

    // Handle /start command
    protected function handleStartCommand($chatId, $userId, Nutgram $bot)
    {
        $message = "Hello! I'm here to assist you. Please follow the instructions to get started.";
        $bot->sendMessage(['chat_id' => $chatId, 'text' => $message]);
    }

    // Handle callback query (when user presses inline buttons)
    protected function handleCallbackQuery($callbackQuery, Nutgram $bot)
    {
        $chatId = $callbackQuery['message']['chat']['id'];
        $userId = $callbackQuery['from']['id'];

        $notSubscribedChannels = [];
        foreach ($this->channels as $channel) {
            if (!$this->isUserMemberOfChannel($userId, $channel['id'])) {
                $notSubscribedChannels[] = $channel['link'];
            }
        }

        if (!empty($notSubscribedChannels)) {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => "You are not subscribed to all channels. Please subscribe to the following:\n\n" . implode("\n", $notSubscribedChannels)
            ]);
            return;
        }

        $this->saveUserSubscription($userId);
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => "Thank you! You can now use the bot. 🎉",
            'reply_markup' => json_encode(['remove_keyboard' => true])
        ]);
    }

    // Check if the user is a member of a channel
    protected function isUserMemberOfChannel($userId, $channelId)
    {
        $apiKey = env('TELEGRAM_BOT_TOKEN');
        $response = Http::get("https://api.telegram.org/bot{$apiKey}/getChatMember", [
            'chat_id' => $channelId,
            'user_id' => $userId
        ]);

        $data = $response->json();
        Log::info("Telegram API response: " . json_encode($data));

        if (!isset($data['ok']) || !$data['ok']) {
            Log::error("API error: " . json_encode($data));
            return false;
        }

        return isset($data['result']['status']) && in_array($data['result']['status'], ['member', 'administrator', 'creator']);
    }

    // Save user subscription status
    protected function saveUserSubscription($userId)
    {
        Cache::put("user_subscribed_{$userId}", true, now()->addDays(30));
    }

    // Check if user is already subscribed
    protected function isUserSubscribed($userId)
    {
        return Cache::has("user_subscribed_{$userId}");
    }

    // Request content from Gemini API based on user input
    protected function handleGeminiRequest($chatId, $text, Nutgram $bot)
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            Log::error('GEMINI_API_KEY is not set in .env');
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => "AI service is currently unavailable. Please try again later."
            ]);
            return;
        }

        // Send request to Gemini API
        $response = Http::post('https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ]
        ]);

        Log::info('Google Gemini API Response: ', $response->json());

        $geminiResponse = $response->json();
        if (isset($geminiResponse['error'])) {
            Log::error('Google Gemini API error: ' . json_encode($geminiResponse['error']));
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => "AI service is currently unavailable. Please try again later."
            ]);
            return;
        }

        // Extract content from the Gemini response
        $replyText = $geminiResponse['candidates'][0]['content']['parts'][0]['text'] ?? "Sorry, I couldn't respond.";

        // Send response to user
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $replyText
        ]);
    }
}
