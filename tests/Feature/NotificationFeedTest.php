<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\FeedbackActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationFeedTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['role' => 'member']);
    }

    private function pushNotification(User $user, array $overrides = []): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create(array_merge([
            'id' => $id,
            'type' => FeedbackActivity::class,
            'data' => ['title' => '💬 피드백 의견', 'body' => '확인 부탁드립니다.', 'url' => '/feedback'],
        ], $overrides));

        return $id;
    }

    public function test_notification_classes_include_database_channel(): void
    {
        $user = $this->member();
        $notification = new FeedbackActivity('제목', '내용', 'tag-1');

        $this->assertContains('database', $notification->via($user));
        $this->assertSame(
            ['title' => '제목', 'body' => '내용', 'url' => '/feedback'],
            $notification->toArray($user)
        );
    }

    public function test_index_returns_items_and_unread_count(): void
    {
        $user = $this->member();
        $this->pushNotification($user);
        $this->pushNotification($user, ['read_at' => now()]);

        $res = $this->actingAs($user)->getJson('/api/notifications');

        $res->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.title', '💬 피드백 의견')
            ->assertJsonPath('items.0.url', '/feedback');
    }

    public function test_notifications_are_scoped_to_user(): void
    {
        $owner = $this->member();
        $other = $this->member();
        $this->pushNotification($owner);

        $this->actingAs($other)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread', 0)
            ->assertJsonCount(0, 'items');
    }

    public function test_mark_single_and_all_read(): void
    {
        $user = $this->member();
        $first = $this->pushNotification($user);
        $this->pushNotification($user);

        // 단건 읽음
        $this->actingAs($user)->postJson('/api/notifications/read', ['id' => $first])->assertOk();
        $this->assertSame(1, $user->unreadNotifications()->count());
        $this->assertNotNull($user->notifications()->find($first)->read_at);

        // 전체 읽음
        $this->actingAs($user)->postJson('/api/notifications/read')->assertOk();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_layout_renders_notification_bell(): void
    {
        $res = $this->actingAs($this->member())->get('/');

        $res->assertOk()
            ->assertSee('id="notifBell"', false)
            ->assertSee('id="notifPanel"', false)
            ->assertSee('/api/notifications', false);
    }
}
