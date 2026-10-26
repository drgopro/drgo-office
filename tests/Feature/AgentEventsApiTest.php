<?php

namespace Tests\Feature;

use App\Models\AgentEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** AI 에이전트 이벤트 API — 훅 수신(토큰) + 화면 폴링/보고서(관리자) */
class AgentEventsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.agent_events.token' => 'test-agent-token']);
    }

    private function event(array $over = []): array
    {
        return array_merge([
            'session_id' => 'sess-1',
            'project' => 'drgo-office',
            'event' => 'PreToolUse',
            'node' => 'claude',
            'summary' => '파일 수정: InvoiceController.php',
            'ts' => '2026-10-26T17:02:11+09:00',
        ], $over);
    }

    public function test_ingest_rejects_wrong_or_missing_token(): void
    {
        $this->postJson('/api/agent-events/ingest', ['events' => [$this->event()]], ['X-Agent-Token' => 'nope'])->assertUnauthorized();
        $this->postJson('/api/agent-events/ingest', ['events' => [$this->event()]])->assertUnauthorized();
        $this->assertDatabaseCount('agent_events', 0);
    }

    public function test_ingest_rejects_when_token_not_configured(): void
    {
        config(['services.agent_events.token' => '']);

        $this->postJson('/api/agent-events/ingest', ['events' => [$this->event()]], ['X-Agent-Token' => ''])->assertUnauthorized();
    }

    public function test_ingest_get_is_connection_test_only(): void
    {
        $this->getJson('/api/agent-events/ingest', ['X-Agent-Token' => 'test-agent-token'])
            ->assertOk()->assertJson(['token_configured' => true, 'token_valid' => true]);
        $this->assertDatabaseCount('agent_events', 0);
    }

    public function test_ingest_stores_batch_with_prompt_and_report(): void
    {
        $events = [
            $this->event(['event' => 'UserPromptSubmit', 'node' => 'user', 'summary' => '지시 전달 (12자)', 'prompt' => '인보이스 반올림 고쳐줘']),
            $this->event(['event' => 'PostToolUse', 'node' => 'codex', 'summary' => 'codex 리뷰 완료', 'duration_ms' => 8500,
                'report' => ['type' => 'review', 'title' => '변경분 리뷰', 'note' => '지적 1건', 'items' => [['t' => 'null 처리 누락', 'ok' => true]]]]),
        ];

        $res = $this->postJson('/api/agent-events/ingest', ['events' => $events], ['X-Agent-Token' => 'test-agent-token'])
            ->assertOk()->assertJson(['ok' => true, 'stored' => 2]);

        $this->assertDatabaseCount('agent_events', 2);
        $last = AgentEvent::find($res->json('last_id'));
        $this->assertSame('codex', $last->node);
        $this->assertSame('review', $last->report['type']);
        $this->assertSame(8500, $last->duration_ms);
        $this->assertSame('2026-10-26 17:02:11', $last->occurred_at->format('Y-m-d H:i:s'));
    }

    public function test_ingest_validates_event_and_node_values(): void
    {
        $this->postJson('/api/agent-events/ingest', ['events' => [$this->event(['event' => 'Bogus'])]], ['X-Agent-Token' => 'test-agent-token'])
            ->assertUnprocessable();
        $this->postJson('/api/agent-events/ingest', ['events' => [$this->event(['node' => 'mars'])]], ['X-Agent-Token' => 'test-agent-token'])
            ->assertUnprocessable();
    }

    public function test_ingest_skips_events_already_stored_by_uid(): void
    {
        $ev = $this->event(['uid' => 'u-1']);
        $this->postJson('/api/agent-events/ingest', ['events' => [$ev]], ['X-Agent-Token' => 'test-agent-token'])->assertOk()->assertJson(['stored' => 1, 'skipped' => 0]);
        $this->postJson('/api/agent-events/ingest', ['events' => [$ev, $this->event(['uid' => 'u-2'])]], ['X-Agent-Token' => 'test-agent-token'])
            ->assertOk()->assertJson(['stored' => 1, 'skipped' => 1]);
        $this->assertDatabaseCount('agent_events', 2);
    }

    public function test_index_rejects_impossible_dates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/agent-events?date=2026-02-31')->assertStatus(422);
        $this->actingAs($admin)->getJson('/api/agent-reports?date=2026-99-99')->assertStatus(422);
        $this->actingAs($admin)->getJson('/api/agent-reports?date=2026-02-28')->assertOk()->assertJson(['date' => '2026-02-28']);
    }

    public function test_index_requires_admin(): void
    {
        $this->getJson('/api/agent-events')->assertUnauthorized();   // 비로그인 (actingAs 이전에 확인)

        $member = User::factory()->create(['role' => 'member']);
        $this->actingAs($member)->getJson('/api/agent-events')->assertForbidden();
        $this->actingAs($member)->getJson('/api/agent-reports')->assertForbidden();
    }

    public function test_index_returns_todays_events_then_since_cursor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $today = now()->format('Y-m-d');
        AgentEvent::create($this->event(['occurred_at' => now()->subDay()]) + ['occurred_at' => now()->subDay()]);
        $a = AgentEvent::create(array_merge($this->event(), ['occurred_at' => now()->subMinutes(5)]));
        $b = AgentEvent::create(array_merge($this->event(['event' => 'PostToolUse']), ['occurred_at' => now()->subMinutes(4)]));
        AgentEvent::create(array_merge($this->event(['project' => 'animals']), ['occurred_at' => now()]));  // 다른 프로젝트는 제외

        $res = $this->actingAs($admin)->getJson('/api/agent-events?date='.$today)->assertOk();
        $this->assertSame([$a->id, $b->id], array_column($res->json('events'), 'id'));
        $this->assertSame($b->id, $res->json('last_id'));

        $res2 = $this->actingAs($admin)->getJson('/api/agent-events?since='.$a->id)->assertOk();
        $this->assertSame([$b->id], array_column($res2->json('events'), 'id'));
        $this->assertSame('PostToolUse', $res2->json('events.0.event'));
        $this->assertArrayHasKey('ts', $res2->json('events.0'));
    }

    public function test_reports_returns_day_reports_counts_and_dates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $base = ['occurred_at' => now()->setTime(10, 30)];
        AgentEvent::create(array_merge($this->event(['event' => 'UserPromptSubmit', 'node' => 'user']), $base));
        AgentEvent::create(array_merge($this->event(['event' => 'PostToolUse', 'node' => 'codex',
            'report' => ['type' => 'review', 'title' => '리뷰 A', 'items' => [['t' => '지적', 'ok' => false]]]]), $base));
        AgentEvent::create(array_merge($this->event(['event' => 'PostToolUse', 'node' => 'git',
            'report' => ['type' => 'commit', 'title' => 'fix: 반올림']]), $base));
        AgentEvent::create(array_merge($this->event(['event' => 'PostToolUse', 'node' => 'github']), $base));
        AgentEvent::create(array_merge($this->event(['event' => 'PostToolUse', 'node' => 'codex',
            'report' => ['type' => 'review', 'title' => '어제 리뷰']]), ['occurred_at' => now()->subDay()->setTime(9, 0)]));

        $res = $this->actingAs($admin)->getJson('/api/agent-reports')->assertOk();
        $this->assertSame(['리뷰 A', 'fix: 반올림'], array_column($res->json('reports'), 'title'));
        $this->assertSame('10:30', $res->json('reports.0.time'));
        $this->assertSame(['prompts' => 1, 'reviews' => 1, 'commits' => 1, 'pushes' => 1], $res->json('counts'));
        $this->assertSame([now()->format('Y-m-d'), now()->subDay()->format('Y-m-d')], $res->json('dates'));

        $yesterday = $this->actingAs($admin)->getJson('/api/agent-reports?date='.now()->subDay()->format('Y-m-d'))->assertOk();
        $this->assertSame(['어제 리뷰'], array_column($yesterday->json('reports'), 'title'));
        $this->assertSame(0, $yesterday->json('counts.prompts'));
    }
}
