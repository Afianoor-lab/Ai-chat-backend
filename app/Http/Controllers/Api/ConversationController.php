<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $conversations = $request->user()
            ->conversations()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'data' => $conversations->items(),
            'links' => [
                'first' => $conversations->url(1),
                'last' => $conversations->url($conversations->lastPage()),
                'prev' => $conversations->previousPageUrl(),
                'next' => $conversations->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'from' => $conversations->firstItem(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'to' => $conversations->lastItem(),
                'total' => $conversations->total(),
            ],
        ]);
    }

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

    public function show(Request $request, string $conversationId): JsonResponse
    {
        $conversation = $request->user()
            ->conversations()
            ->with(['messages' => fn ($query) => $query->orderBy('created_at')->orderBy('id')])
            ->findOrFail($conversationId);

        return response()->json(['data' => $conversation]);
    }

    public function destroy(Request $request, string $conversationId): Response
    {
        $request->user()
            ->conversations()
            ->findOrFail($conversationId)
            ->delete();

        return response()->noContent();
    }
}
