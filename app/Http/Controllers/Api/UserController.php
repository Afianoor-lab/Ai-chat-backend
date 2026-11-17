<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->profileData($request),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($request->user()->getKey()),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->fill(collect($validated)->only(['name', 'email', 'password'])->all());
        $request->user()->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => $this->profileData($request),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        if (! $request->exists('openrouter_api_key') && ! $request->exists('default_model')) {
            throw ValidationException::withMessages([
                'settings' => ['Provide an OpenRouter API key or a default model to update.'],
            ]);
        }

        $validated = $request->validate([
            'openrouter_api_key' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'default_model' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $request->user()->fill($validated);
        $request->user()->save();

        return response()->json([
            'message' => 'Settings updated successfully.',
            'data' => $this->profileData($request),
        ]);
    }

    private function profileData(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'api_key_configured' => filled($user->openrouter_api_key),
            'default_model' => $user->default_model,
        ];
    }
}
