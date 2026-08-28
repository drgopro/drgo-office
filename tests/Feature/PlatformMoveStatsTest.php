<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 통계 — 플랫폼 이동 수요 (의뢰자 플랫폼 변경 로그 기반) */
class PlatformMoveStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_moves_from_change_logs(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        // 숲 → 치지직 이동 (제거+추가 동시)
        $mover = Client::create(['nickname' => '이동고블린', 'grade' => 'normal', 'platforms' => ['숲']]);
        $mover->update(['platforms' => ['치지직']]);

        // 추가만 한 경우 — 이동으로 집계하면 안 됨
        $adder = Client::create(['nickname' => '추가고블린', 'grade' => 'normal', 'platforms' => ['유튜브']]);
        $adder->update(['platforms' => ['유튜브', '치지직']]);

        $res = $this->get('/marketing-report')->assertOk()
            ->assertSee('플랫폼 이동 수요')
            ->assertSee('플랫폼 이동 추이');

        $moves = $res->viewData('platformMoves');
        $this->assertCount(1, $moves);
        $move = array_values($moves)[0];
        $this->assertSame('숲', $move['from']);
        $this->assertSame('치지직', $move['to']);
        $this->assertSame(1, $move['count']);
        $this->assertSame('이동고블린', $move['clients'][0]['name']); // 상세 펼침의 '누가'

        // 추이 — 이번 달 1건
        $trend = $res->viewData('platformMoveTrend');
        $this->assertSame(1, $trend[now()->format('Y.m')]);
    }

    public function test_no_moves_shows_empty_state(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->get('/marketing-report')->assertOk()
            ->assertSee('기간 내 플랫폼 이동 없음');
    }
}
