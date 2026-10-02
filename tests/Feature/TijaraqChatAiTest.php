<?php

namespace Tests\Feature;

use App\Models\User;
use Common\Auth\Actions\CreateUser;
use Common\Auth\Permissions\Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TijaraqChatAiTest extends TestCase
{
    use DatabaseTransactions;

    private function customer(): User
    {
        return (new CreateUser())->execute([
            'email' => 'chat-' . uniqid() . '@example.test',
            'password' => 'Secret-12345',
            'name' => 'Chat Customer',
            'email_verified_at' => now(),
        ]);
    }

    private function asUser(User $user): static
    {
        return $this->actingAs($user, 'sanctum')->withHeaders([
            'Referer' => config('app.url') . '/',
            'Origin' => config('app.url'),
        ]);
    }

    public function test_customer_can_chat_but_another_customer_cannot_read_or_reply(): void
    {
        $owner = $this->customer();
        $other = $this->customer();

        $response = $this->asUser($owner)->postJson('/api/v1/tijaraq-chat/conversations', [
            'message' => '<script>alert(1)</script>Hello support',
        ])->assertCreated();
        $id = $response->json('conversation_id');

        $this->assertDatabaseHas('conversations', ['id' => $id, 'user_id' => $owner->id, 'channel' => 'livechat']);
        $this->asUser($owner)->getJson("/api/v1/tijaraq-chat/conversations/$id")
            ->assertOk()->assertDontSee('<script>', false);
        $this->asUser($owner)->postJson("/api/v1/tijaraq-chat/conversations/$id/messages", [
            'message' => 'More details',
        ])->assertCreated();

        $this->asUser($other)->getJson("/api/v1/tijaraq-chat/conversations/$id")->assertForbidden();
        $this->asUser($other)->postJson("/api/v1/tijaraq-chat/conversations/$id/messages", [
            'message' => 'Unauthorized',
        ])->assertForbidden();
        $this->asUser($other)->getJson('/api/v1/tijaraq-chat/conversations')
            ->assertJsonMissing(['id' => $id]);
    }

    public function test_chat_returns_the_latest_messages_in_chronological_order(): void
    {
        $customer = $this->customer();
        $id = $this->asUser($customer)->postJson('/api/v1/tijaraq-chat/conversations', [
            'message' => 'First message',
        ])->assertCreated()->json('conversation_id');

        $messages = [];
        for ($number = 1; $number <= 205; $number++) {
            $messages[] = [
                'conversation_id' => $id,
                'user_id' => $customer->id,
                'author' => 'user',
                'type' => 'message',
                'body' => "Message $number",
                'uuid' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('conversation_items')->insert($messages);

        $response = $this->asUser($customer)->getJson("/api/v1/tijaraq-chat/conversations/$id")
            ->assertOk();
        $returned = $response->json('messages');
        $this->assertCount(200, $returned);
        $this->assertSame('Message 6', $returned[0]['body']);
        $this->assertSame('Message 205', $returned[199]['body']);
    }

    public function test_chat_broadcast_channels_are_private(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => 'test-app',
        ]);
        require base_path('routes/channels.php');
        $owner = $this->customer();
        $other = $this->customer();
        $payload = [
            'socket_id' => '123.456',
            'channel_name' => 'private-tijaraq-chat-user.' . $owner->id,
        ];

        $this->actingAs($owner, 'web')->postJson('/broadcasting/auth', $payload)->assertOk();
        $this->actingAs($other, 'web')->postJson('/broadcasting/auth', $payload)->assertUnauthorized();
        $this->actingAs($owner, 'web')->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-tijaraq-chat-agents',
        ])->assertForbidden();
    }

    public function test_agent_can_join_chat_broadcast_channel(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => 'test-app',
        ]);
        require base_path('routes/channels.php');
        $agent = (new CreateUser())->execute([
            'email' => 'chat-agent-' . uniqid() . '@example.test',
            'password' => 'Secret-12345',
            'name' => 'Chat Agent',
            'email_verified_at' => now(),
            'type' => 'agent',
            'permissions' => [['id' => Permission::where('name', 'conversations.update')->value('id')]],
        ]);
        $this->actingAs($agent, 'web')->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-tijaraq-chat-agents',
        ])->assertOk();
    }

    public function test_customer_cannot_use_agent_or_ai_endpoints(): void
    {
        $this->asUser($this->customer())
            ->getJson('/api/v1/tijaraq-chat/conversations?agent=1')->assertForbidden();
        $this->asUser($this->customer())
            ->postJson('/api/v1/tijaraq-ai/ask', ['prompt' => 'Write a reply'])->assertForbidden();
    }

    public function test_ai_settings_do_not_expose_provider_keys(): void
    {
        config(['services.openai.api_key' => 'test-secret-never-return-this']);
        $admin = (new CreateUser())->execute([
            'email' => 'chat-admin-' . uniqid() . '@example.test',
            'password' => 'Secret-12345',
            'name' => 'Chat Admin',
            'email_verified_at' => now(),
            'type' => 'agent',
            'permissions' => [['id' => Permission::where('name', 'admin')->value('id')]],
        ]);

        $this->asUser($admin)->getJson('/api/v1/admin/tijaraq-ai')
            ->assertOk()
            ->assertJsonPath('settings.providers.openai.configured', true)
            ->assertDontSee('test-secret-never-return-this');
    }

    public function test_agent_sees_configuration_error_before_ai_provider_call(): void
    {
        config(['services.llm_provider' => 'openai', 'services.openai.api_key' => null]);
        $agent = (new CreateUser())->execute([
            'email' => 'chat-agent-' . uniqid() . '@example.test',
            'password' => 'Secret-12345',
            'name' => 'Chat Agent',
            'email_verified_at' => now(),
            'type' => 'agent',
            'permissions' => [['id' => Permission::where('name', 'conversations.update')->value('id')]],
        ]);

        $this->asUser($agent)->postJson('/api/v1/tijaraq-ai/ask', [
            'prompt' => 'Draft a reply',
        ])->assertStatus(422);
    }

    public function test_agent_can_read_and_reply_to_customer_chat(): void
    {
        Mail::fake();
        $customer = $this->customer();
        $id = $this->asUser($customer)->postJson('/api/v1/tijaraq-chat/conversations', [
            'message' => 'Need support',
        ])->assertCreated()->json('conversation_id');
        $agent = (new CreateUser())->execute([
            'email' => 'chat-agent-' . uniqid() . '@example.test',
            'password' => 'Secret-12345',
            'name' => 'Chat Agent',
            'email_verified_at' => now(),
            'type' => 'agent',
            'permissions' => [['id' => Permission::where('name', 'conversations.update')->value('id')]],
        ]);

        $this->asUser($agent)->getJson('/api/v1/tijaraq-chat/conversations?agent=1')
            ->assertOk()
            ->assertJsonFragment(['id' => $id])
            ->assertJsonPath('conversations.0.user.name', $customer->name)
            ->assertJsonPath('conversations.0.latest_message.body', 'Need support');
        $this->asUser($agent)->postJson("/api/v1/tijaraq-chat/conversations/$id/messages", [
            'message' => 'We can help',
        ])->assertCreated();
        $this->asUser($customer)->getJson("/api/v1/tijaraq-chat/conversations/$id")
            ->assertOk()->assertSee('We can help');
    }

    public function test_admin_can_disable_new_chat_messages(): void
    {
        $admin = (new CreateUser())->execute([
            'email' => 'chat-admin-' . uniqid() . '@example.test',
            'password' => 'Secret-12345',
            'name' => 'Chat Admin',
            'email_verified_at' => now(),
            'type' => 'agent',
            'permissions' => [['id' => Permission::where('name', 'admin')->value('id')]],
        ]);
        $this->asUser($admin)->putJson('/api/v1/admin/tijaraq-chat', ['enabled' => false])
            ->assertOk()->assertJsonPath('enabled', false);
        $this->asUser($admin)->getJson('/api/v1/admin/tijaraq-chat')
            ->assertOk()->assertJsonPath('enabled', false);
        $this->asUser($this->customer())->postJson('/api/v1/tijaraq-chat/conversations', [
            'message' => 'Disabled chat',
        ])->assertForbidden();
    }
}
