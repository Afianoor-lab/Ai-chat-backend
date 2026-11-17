<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'archived' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $conversations = $request->user()
            ->conversations()
            ->where('locked', false)
            ->where('archived', $validated['archived'] ?? false)
            ->when(filled($validated['q'] ?? null), function (Builder $query) use ($validated): void {
                $search = $validated['q'];

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('messages', fn (Builder $messages): Builder => $messages
                            ->where('content', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

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
        $user = $request->user();
        $validated = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'persona' => ['sometimes', 'nullable', 'string', 'max:255'],
            'project_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where('user_id', $user->getKey()),
            ],
        ]);

        $model = $validated['model'] ?? $user->default_model;

        if (blank($model)) {
            throw ValidationException::withMessages([
                'model' => ['Choose a model or save a default model in your settings.'],
            ]);
        }

        $conversation = $user->conversations()->create([
            'title' => $validated['title'] ?? 'New Chat',
            'model' => $model,
            'persona' => $validated['persona'] ?? null,
            'project_id' => $validated['project_id'] ?? null,
        ]);

        return response()->json([
            'data' => $conversation->only([
                'id', 'user_id', 'project_id', 'title', 'model', 'persona',
                'pinned', 'archived', 'locked', 'created_at', 'updated_at',
            ]),
        ], 201);
    }

    public function show(Request $request, string $conversationId): JsonResponse
    {
        $conversation = $this->findConversation($request, $conversationId);
        $this->ensureUnlocked($conversation);

        $conversation->load([
            'messages' => fn (HasMany $query) => $query->orderBy('created_at')->orderBy('id'),
            'project',
        ]);

        return response()->json(['data' => $conversation]);
    }

    public function update(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'pinned' => ['sometimes', 'boolean'],
            'archived' => ['sometimes', 'boolean'],
            'model' => ['sometimes', 'required', 'string', 'max:255'],
            'persona' => ['sometimes', 'nullable', 'string', 'max:255'],
            'project_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where('user_id', $user->getKey()),
            ],
        ]);

        $conversation = $this->findConversation($request, $conversationId);
        $this->ensureUnlocked($conversation);
        $conversation->fill($validated)->save();

        return response()->json(['data' => $conversation->fresh()]);
    }

    public function destroy(Request $request, string $conversationId): Response
    {
        $conversation = $this->findConversation($request, $conversationId);
        $this->ensureUnlocked($conversation);
        $conversation->delete();

        return response()->noContent();
    }

    public function destroyAll(Request $request): Response
    {
        $request->user()->conversations()->delete();

        return response()->noContent();
    }

    public function duplicate(Request $request, string $conversationId): JsonResponse
    {
        $source = $this->findConversation($request, $conversationId);
        $this->ensureUnlocked($source);
        $source->load(['messages' => fn (HasMany $query) => $query->orderBy('id')]);

        $duplicate = DB::transaction(function () use ($request, $source): Conversation {
            $conversation = $request->user()->conversations()->create([
                'project_id' => $source->project_id,
                'title' => "{$source->title} (copy)",
                'model' => $source->model,
                'persona' => $source->persona,
                'pinned' => false,
                'archived' => false,
                'locked' => false,
            ]);

            foreach ($source->messages as $message) {
                $conversation->messages()->create($message->only(['role', 'content', 'model']));
            }

            return $conversation;
        });

        return response()->json(['data' => $duplicate->load('messages')], 201);
    }

    public function lock(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();

        if (blank($user->passcode)) {
            return response()->json([
                'message' => 'Set a passcode before locking conversations.',
            ], 409);
        }

        $conversation = $this->findConversation($request, $conversationId);
        $conversation->locked = true;
        $conversation->save();

        return response()->json([
            'message' => 'Conversation locked.',
            'data' => ['id' => $conversation->id, 'locked' => true],
        ]);
    }

    public function unlock(Request $request, string $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();

        if (blank($user->passcode) || ! Hash::check($validated['code'], $user->passcode)) {
            throw ValidationException::withMessages([
                'code' => ['The passcode is incorrect.'],
            ]);
        }

        $conversation = $this->findConversation($request, $conversationId);
        $conversation->locked = false;
        $conversation->save();

        return response()->json([
            'message' => 'Conversation unlocked.',
            'data' => ['id' => $conversation->id, 'locked' => false],
        ]);
    }

    private function findConversation(Request $request, string $conversationId): Conversation
    {
        return $request->user()
            ->conversations()
            ->findOrFail($conversationId);
    }

    private function ensureUnlocked(Conversation $conversation): void
    {
        if ($conversation->locked) {
            abort(423, 'Unlock this conversation before accessing it.');
        }
    }
}
