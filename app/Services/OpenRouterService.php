<?php

namespace App\Services;

use App\Exceptions\AIServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterService
{
    public function chat( string $model, array $messages): array
    {
         $apiKey = request()->user()->openrouter_api_key;
        if (blank($apiKey)) {
            throw new AIServiceException('No OpenRouter API key configured for this account.', 422);
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(60)
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model'    => $model,
                    'messages' => $messages,
                ]);
        } catch (ConnectionException $e) {
            report($e);
            throw new AIServiceException('AI service is temporarily unavailable.', 503);
        }

        if ($response->failed()) {
            $message = $response->status() === 429
                ? 'Model is rate limited, try again shortly or pick another model.'
                : ($response->json('error.message') ?? 'OpenRouter request failed.');

            throw new AIServiceException('AI could not generate a response. ' . $message, $response->status());
        }

        $content = $response->json('choices.0.message.content');
        //print_r($content);

        // if (empty($content)) {
        //     throw new AIServiceException('AI returned an empty response.', 502);
        // }

        return [
            'content'           => $content,
            // 'model'             => $response->json('model'),
            // 'prompt_tokens'     => $response->json('usage.prompt_tokens'),
            // 'completion_tokens' => $response->json('usage.completion_tokens'),
        ];
    }
}