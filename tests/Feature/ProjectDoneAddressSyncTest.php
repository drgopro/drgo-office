<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 프로젝트 완료 시 의뢰자 주소 최신화 — 세팅 장소가 의뢰자 정보와 다르면 덮어쓰고,
 * 변경 전/후 값이 의뢰자 활동 로그에 남는다.
 */
class ProjectDoneAddressSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $this->client = Client::create([
            'nickname' => '고블린', 'grade' => 'normal',
            'address' => '서울 강남구 옛집로 1', 'address_detail' => '101호',
        ]);
    }

    private function makeProject(array $attrs = []): Project
    {
        return Project::create(array_merge([
            'client_id' => $this->client->id, 'name' => '스튜디오 세팅',
            'project_type' => 'visit', 'stage' => 'payment',
            'address' => '경기 수원시 새집로 77', 'address_detail' => '202호',
        ], $attrs));
    }

    public function test_project_done_overwrites_client_address_with_log(): void
    {
        $project = $this->makeProject();

        $this->actingAs($this->admin)->patchJson("/projects/{$project->id}/stage", ['stage' => 'done'])
            ->assertOk();

        $fresh = $this->client->fresh();
        $this->assertSame('경기 수원시 새집로 77', $fresh->address);
        $this->assertSame('202호', $fresh->address_detail);

        // 변경 전/후 값이 의뢰자 활동 로그에 남음
        $log = ActivityLog::where('loggable_type', Client::class)
            ->where('loggable_id', $this->client->id)
            ->where('action', 'update')->latest('id')->first();
        $this->assertNotNull($log);
        $changed = collect($log->changes)->map(fn ($c) => [$c['old'] ?? null, $c['new'] ?? null])->values()->all();
        $this->assertContains(['서울 강남구 옛집로 1', '경기 수원시 새집로 77'], $changed);
    }

    public function test_project_done_with_same_address_leaves_client_untouched(): void
    {
        $project = $this->makeProject(['address' => '서울 강남구 옛집로 1', 'address_detail' => '101호']);
        $before = $this->client->updated_at;

        $this->actingAs($this->admin)->patchJson("/projects/{$project->id}/stage", ['stage' => 'done'])
            ->assertOk();

        $this->assertTrue($this->client->fresh()->updated_at->equalTo($before));
        $this->assertSame(0, ActivityLog::where('loggable_type', Client::class)
            ->where('loggable_id', $this->client->id)->where('action', 'update')->count());
    }

    public function test_project_done_without_address_keeps_client_address(): void
    {
        $project = $this->makeProject(['address' => null, 'address_detail' => null]);

        $this->actingAs($this->admin)->patchJson("/projects/{$project->id}/stage", ['stage' => 'done'])
            ->assertOk();

        $this->assertSame('서울 강남구 옛집로 1', $this->client->fresh()->address);
    }
}
