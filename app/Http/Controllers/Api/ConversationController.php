<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $model = $validated['model'] ?? $user->default_model;

        if (blank($model)) {
            throw ValidationException::withMessages([
                'model' => ['Choose a model or save a default model in your settings.'],
            ]);
        }

        $conversation = $user->conversations()->create([
            'title' => $validated['title'] ?? 'New Chat',
            'model' => $model,
        ]);

        return response()->json([
            'data' => $conversation->only(['id', 'user_id', 'title', 'model', 'created_at', 'updated_at']),
        ], 201);
    }
}
