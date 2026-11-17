<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_uses_the_users_default_model_when_not_provided(): void
    {
        $user = User::factory()->create(['default_model' => 'openai/gpt-4o-mini']);
        Sanctum::actingAs($user);

        $this->postJson('/api/conversations', ['title' => 'First chat'])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.title', 'First chat')
            ->assertJsonPath('data.model', 'openai/gpt-4o-mini');

        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'title' => 'First chat',
            'model' => 'openai/gpt-4o-mini',
        ]);

        $conversation = Conversation::query()->where('title', 'First chat')->firstOrFail();
        $message = $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Hello',
        ]);

        $this->assertSame($conversation->id, $message->conversation->id);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello',
        ]);
    }

    public function test_conversation_requires_a_model_when_no_default_is_saved(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/conversations')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('model');
    }
}
