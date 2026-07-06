<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarSearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchedule(array $attrs = []): Schedule
    {
        return Schedule::create(array_merge([
            'title' => '일정',
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-06',
            'color' => 'gold',
        ], $attrs));
    }

    public function test_search_matches_title_client_and_location(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $this->makeSchedule(['title' => '나리 공덕 세팅']);
        $this->makeSchedule(['title' => '다른 일정', 'client_name' => '나리']);
        $this->makeSchedule(['title' => '또 다른 일정', 'location' => '공덕동 오피스텔']);
        $this->makeSchedule(['title' => '무관한 일정']);

        $byTitle = $this->actingAs($user)->getJson('/api/events/search?q=나리');
        $byTitle->assertOk();
        $this->assertCount(2, $byTitle->json()); // 제목 1 + 의뢰자 1

        $byLocation = $this->actingAs($user)->getJson('/api/events/search?q=공덕');
        $this->assertCount(2, $byLocation->json()); // 제목 1 + 장소 1
    }

    public function test_search_excludes_other_users_private_events(): void
    {
        $owner = User::factory()->create(['role' => 'member']);
        $other = User::factory()->create(['role' => 'member']);
        $this->makeSchedule(['title' => '비공개 미팅', 'is_private' => true, 'created_by' => $owner->id]);

        $this->assertCount(0, $this->actingAs($other)->getJson('/api/events/search?q=비공개')->json());
        $this->assertCount(1, $this->actingAs($owner)->getJson('/api/events/search?q=비공개')->json());
    }

    public function test_guest_gets_empty_results(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $this->makeSchedule(['title' => '검색될 일정']);

        $this->assertCount(0, $this->actingAs($guest)->getJson('/api/events/search?q=검색')->json());
    }

    public function test_empty_query_returns_empty(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $this->makeSchedule(['title' => '아무 일정']);

        $this->assertCount(0, $this->actingAs($user)->getJson('/api/events/search?q=')->json());
    }
}
