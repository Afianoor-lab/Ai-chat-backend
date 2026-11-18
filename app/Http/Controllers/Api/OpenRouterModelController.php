<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterModelController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $apiKey = $request->user()->openrouter_api_key;

        if (blank($apiKey)) {
            return response()->json([
                'message' => 'Configure your OpenRouter API key before requesting models.',
                'errors' => [
                    'openrouter_api_key' => ['An OpenRouter API key is required.'],
                ],
            ], 422);
        }

        try {
            $http = Http::acceptJson()
                ->withToken($apiKey)
                ->timeout(15);

            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }

            $response = $http->get('https://openrouter.ai/api/v1/models');
        } catch (ConnectionException $exception) {
            report($exception);

            return response()->json([
                'message' => 'OpenRouter could not be reached. Please try again later.',
            ], 502);
        }

        if (! $response->successful()) {
            Log::warning('OpenRouter model request failed.', [
                'status' => $response->status(),
            ]);

            return response()->json([
                'message' => 'OpenRouter could not provide the model list.',
            ], 502);
        }

        $models = $response->json('data');

        if (! is_array($models)) {
            Log::warning('OpenRouter returned an invalid model list.');

            return response()->json([
                'message' => 'OpenRouter returned an invalid model list.',
            ], 502);
        }

        foreach ($models as $index => $model) {
            if (! is_array($model)) {
                Log::warning('OpenRouter returned an invalid model entry.', ['index' => $index]);

                return response()->json([
                    'message' => 'OpenRouter returned an invalid model list.',
                ], 502);
            }

            $inputModalities = data_get($model, 'architecture.input_modalities', []);
            $outputModalities = data_get($model, 'architecture.output_modalities', []);
            $supportedParameters = data_get($model, 'supported_parameters', []);
            $modality = strtolower((string) data_get($model, 'architecture.modality', ''));

            $model['vision'] = (bool) ($model['vision'] ?? (
                (is_array($inputModalities) && in_array('image', $inputModalities, true))
                || str_contains($modality, 'image')
            ));
            $model['reasoning'] = (bool) ($model['reasoning'] ?? (
                is_array($supportedParameters)
                && (
                    in_array('reasoning', $supportedParameters, true)
                    || in_array('include_reasoning', $supportedParameters, true)
                )
            ));
            $model['price'] = $model['price'] ?? data_get($model, 'pricing');
            $model['input_modalities'] = is_array($inputModalities) ? $inputModalities : [];
            $model['output_modalities'] = is_array($outputModalities) ? $outputModalities : [];

            $models[$index] = $model;
        }

        return response()->json(['data' => $models]);
    }
}