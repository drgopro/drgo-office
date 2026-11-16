<?php

namespace Tests\Feature;

use App\Jobs\SendChannelTalkGroupMessage;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** 프로젝트 담당자 지정 알림 — 새로 담당자가 된 사람을 채널톡 기본 톡방(아웃바운드)에서 멘션 */
class ProjectAssigneeAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $kim;

    private User $lee;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.channeltalk.access_key' => 'test-key',
            'services.channeltalk.access_secret' => 'test-secret',
            'services.channeltalk.group' => '아웃바운드',
        ]);
        $this->admin = User::factory()->create(['role' => 'admin', 'display_name' => '관리자']);
        $this->kim = User::factory()->create(['role' => 'member', 'display_name' => '김태한']);
        $this->lee = User::factory()->create(['role' => 'member', 'display_name' => '이수호']);
        $this->client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        Queue::fake();
    }

    /** 멘션 자리표시자(발송 시점에 실제 멘션으로 바뀜)가 본문에 들어 있는지 */
    private function mentions(string $text, User $user): bool
    {
        return str_contains($text, base64_encode((string) json_encode([$user->email, $user->display_name])));
    }

    /** @return array<int, string> 큐에 쌓인 채널톡 알림 본문 */
    private function alerts(): array
    {
        return Queue::pushed(SendChannelTalkGroupMessage::class)->map(fn ($job) => $job->text)->values()->all();
    }

    public function test_creating_project_alerts_assignees_in_default_room_except_the_creator(): void
    {
        $this->actingAs($this->admin)->postJson("/clients/{$this->client->id}/projects", [
            'name' => '스튜디오 세팅', 'project_type' => 'visit',
            'assigned_user_id' => $this->kim->id,
            'extra_assignee_ids' => [$this->lee->id, $this->admin->id], // 본인(작성자)은 알리지 않음
        ])->assertCreated();

        $alerts = $this->alerts();
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString("'고블린 · 스튜디오 세팅' 프로젝트의 담당자로 지정되었습니다", $alerts[0]);
        $this->assertTrue($this->mentions($alerts[0], $this->kim));
        $this->assertTrue($this->mentions($alerts[0], $this->lee));
        $this->assertFalse($this->mentions($alerts[0], $this->admin));
        $this->assertStringContainsString('/projects/', $alerts[0]);
        // 톡방 지정 없음 = 기본 팀챗 그룹(아웃바운드)
        Queue::assertPushed(SendChannelTalkGroupMessage::class, fn ($job) => $job->group === null);
    }

    public function test_creating_project_assigned_to_self_sends_nothing(): void
    {
        $this->actingAs($this->admin)->postJson("/clients/{$this->client->id}/projects", ['name' => '내가 맡는 프로젝트', 'project_type' => 'visit'])->assertCreated();

        $this->assertSame([], $this->alerts());
    }

    public function test_update_alerts_only_newly_added_assignees(): void
    {
        $project = Project::create(['client_id' => $this->client->id, 'name' => '세팅', 'project_type' => 'visit', 'stage' => 'consulting', 'assigned_user_id' => $this->kim->id]);

        // 추가 담당자 지정 → 새로 들어온 사람만
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", ['extra_assignee_ids' => [$this->lee->id]])->assertOk();
        $this->assertCount(1, $this->alerts());
        $this->assertTrue($this->mentions($this->alerts()[0], $this->lee));
        $this->assertFalse($this->mentions($this->alerts()[0], $this->kim));

        // 대표↔추가 자리만 바뀌면 알리지 않는다 (두 사람 모두 이미 담당자)
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", ['assigned_user_id' => $this->lee->id, 'extra_assignee_ids' => [$this->kim->id]])->assertOk();
        $this->assertCount(1, $this->alerts());

        // 담당자와 무관한 수정, 같은 담당자 재저장은 알리지 않는다
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", ['name' => '이름 수정'])->assertOk();
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", ['assigned_user_id' => $this->lee->id, 'extra_assignee_ids' => [$this->kim->id]])->assertOk();
        $this->assertCount(1, $this->alerts());

        // 담당자 제외는 알리지 않는다
        $this->actingAs($this->admin)->patchJson("/api/projects/{$project->id}", ['extra_assignee_ids' => []])->assertOk();
        $this->assertCount(1, $this->alerts());
    }

    public function test_nothing_is_sent_when_channeltalk_is_not_configured(): void
    {
        config(['services.channeltalk.access_key' => '']);

        $this->actingAs($this->admin)->postJson("/clients/{$this->client->id}/projects", [
            'name' => '세팅', 'project_type' => 'visit', 'assigned_user_id' => $this->kim->id,
        ])->assertCreated();

        $this->assertSame([], $this->alerts());
    }
}
