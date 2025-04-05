<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;
    protected $model;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        $this->model = env('GEMINI_MODEL', 'gemini-pro');
        $this->apiUrl = rtrim(env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models'), '/');
    }

    public function generateResponse(string $prompt): string
    {
        try {
            $fullUrl = "{$this->apiUrl}/{$this->model}:generateContent?key={$this->apiKey}";

            Log::debug("Gemini API URL: {$fullUrl}");
            Log::debug("Prompt: {$prompt}");

            $response = Http::timeout(30)
                ->retry(3, 100)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($fullUrl, [
                    'contents' => [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.9,
                        'topP' => 1,
                        'maxOutputTokens' => 2048
                    ]
                ]);

            Log::debug("Gemini API Response: ", $response->json());

            $data = $response->json();

            // Agar javob muvaffaqiyatli bo'lsa
            if ($response->successful() && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }

            // Xatolik yuz berdi
            $error = $data['error']['message'] ?? 'Noma’lum xatolik';
            Log::error('Gemini API error: ' . $error);

            // Xatolik haqida foydalanuvchiga xabar yuborish
            return "Kechirasiz, javob olishda xatolik yuz berdi: " . $error;

        } catch (\Exception $e) {
            // Exceptionni logga yozish
            Log::error('Gemini service error: ' . $e->getMessage());
            // Foydalanuvchiga xabar yuborish
            return "Kechirasiz, xatolik yuz berdi. Iltimos, keyinroq urunib ko'ring. 2";
        }
    }
}
