<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\OpenRouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Response;
use RuntimeException;

class MessageController extends Controller
{
   public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()
            ->conversations()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20);

        return Response::success('Recent conversations retrieved.', [
            'conversations' => $conversations->getCollection()
                ->map(fn (Conversation $conversation): array => $this->conversationSummary($conversation))
                ->values(),
        ], 200, [
            'current_page' => $conversations->currentPage(),
            'last_page' => $conversations->lastPage(),
            'per_page' => $conversations->perPage(),
            'total' => $conversations->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $conversation = $request->user()->conversations()->create([
            'title' => null,
        ]);

        return Response::success('Conversation created.', [
            'conversation' => $this->conversationSummary($conversation),
        ], 201);
    }

    public function show(Request $request, int $conversationId): JsonResponse
    {
        $conversation = $request->user()
            ->conversations()
            ->with(['messages' => fn ($query) => $query->orderBy('created_at','desc')->orderBy('id')])
            ->findOrFail($conversationId);

        return Response::success('Conversation retrieved.', [
            'conversation' => [
                ...$this->conversationSummary($conversation),
                'messages' => $conversation->messages
                    ->map(fn (Message $message): array => [
                        'id' => $message->id,
                        'role' => $message->role,
                        'content' => $message->content,
                        'model_id' => $message->model,
                        'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                    ])
                    ->values(),
            ],
        ]);
    }

    public function destroy(Request $request, int $conversationId): JsonResponse
    {
        $conversation = $request->user()
            ->conversations()
            ->findOrFail($conversationId);

        $conversation->delete();

        return Response::success('Conversation deleted.');
    }

    public function chat(Request $request, OpenRouterService $openRouter,Conversation $id): JsonResponse
    {
        $validated = $request->validate([
            'model'   => ['required', 'string'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

         $conversation=$id;

   
            $reply = $openRouter->chat($validated['model'], [
                ['role' => 'user', 'content' => $validated['message']],
            ]);
       
         
         $assistantContent =  $reply['content'];


        if (! is_string($assistantContent) || trim($assistantContent) === '') {


            return Response::error('AI service returned an invalid response.', 502);
        }

        [$userMessage, $assistantMessage] = DB::transaction(function () use (
            $conversation,
            $validated,
            $assistantContent
        ): array {
            if ($conversation->title === null || trim($conversation->title) === '') {
                $conversation->title = Str::of($validated['content'])->squish()->limit(80)->toString();
            }

            $conversation->save();

            $userMessage = $conversation->messages()->create([
                'role' => 'user',
                'content' => $validated['message'],
                'model' => $validated['model'],
            ]);
            $assistantMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $assistantContent,
                'model' => $validated['model'],
            ]);

            return [$userMessage, $assistantMessage];
        });

        return Response::success('Message sent successfully.', [
            'conversation' => $this->conversationSummary($conversation->fresh()),
            'messages' => [
                $this->messageData($userMessage),
                $this->messageData($assistantMessage),
            ],
        ], 201);
    }

    private function conversationSummary(Conversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'created_at' => $conversation->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $conversation->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    private function messageData(Message $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'model' => $message->model,
            'created_at' => $message->created_at->format('Y-m-d H:i:s'),
        ];
    }
}

    

