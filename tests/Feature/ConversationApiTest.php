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

    public function test_history_lists_only_the_authenticated_users_conversations_with_pagination(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $user->conversations()->create(['title' => 'First chat', 'model' => 'model-a']);
        $user->conversations()->create(['title' => 'Second chat', 'model' => 'model-a']);
        $otherUser->conversations()->create(['title' => 'Private chat', 'model' => 'model-b']);

        Sanctum::actingAs($user);

        $this->getJson('/api/conversations?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['title' => 'Private chat']);
    }

    public function test_history_details_include_messages_in_creation_order(): void
    {
        $user = User::factory()->create();
        $conversation = $user->conversations()->create([
            'title' => 'Chat history',
            'model' => 'model-a',
        ]);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Question']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => 'Answer']);

        Sanctum::actingAs($user);

        $this->getJson("/api/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->id)
            ->assertJsonPath('data.messages.0.content', 'Question')
            ->assertJsonPath('data.messages.1.content', 'Answer');
    }

    public function test_users_cannot_view_or_delete_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $conversation = $owner->conversations()->create([
            'title' => 'Private chat',
            'model' => 'model-a',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/conversations/{$conversation->id}")->assertNotFound();
        $this->deleteJson("/api/conversations/{$conversation->id}")->assertNotFound();

        $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
    }

    public function test_deleting_a_conversation_also_deletes_its_messages(): void
    {
        $user = User::factory()->create();
        $conversation = $user->conversations()->create([
            'title' => 'Delete me',
            'model' => 'model-a',
        ]);
        $message = $conversation->messages()->create([
            'role' => 'user',
            'content' => 'This message should be deleted too',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/conversations/{$conversation->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }
}
