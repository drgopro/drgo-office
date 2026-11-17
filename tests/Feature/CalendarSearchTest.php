<?php

namespace Tests\Feature;

use App\Models\Assignee;
use App\Models\Client;
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
        // 날짜는 그대로 Y-m-d — Carbon 그대로 직렬화하면 UTC ISO로 하루 전이 되는 회귀 방지
        $this->assertSame('2026-07-06', $byTitle->json()[0]['start_date']);
        $this->assertSame('2026-07-06', $byTitle->json()[0]['end_date']);

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

    public function test_search_filters_by_multiple_categories_and_assignees(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $this->makeSchedule(['title' => '세팅 A', 'color' => 'gold']);
        $this->makeSchedule(['title' => '세팅 B', 'color' => 'teal']);
        $this->makeSchedule(['title' => '세팅 C', 'color' => 'blue']);

        // 다중 카테고리 — gold + teal
        $multi = $this->actingAs($user)->getJson('/api/events/search?q=세팅&colors[]=gold&colors[]=teal');
        $this->assertEqualsCanonicalizing(['세팅 A', '세팅 B'], collect($multi->json())->pluck('title')->all());

        // 다중 담당자
        $a1 = Assignee::create(['name' => '이수호']);
        $a2 = Assignee::create(['name' => '김광래']);
        Schedule::where('title', '세팅 A')->first()->assignees()->attach($a1->id);
        Schedule::where('title', '세팅 C')->first()->assignees()->attach($a2->id);
        $byAsg = $this->actingAs($user)->getJson("/api/events/search?q=&assignee_ids[]={$a1->id}&assignee_ids[]={$a2->id}");
        $this->assertEqualsCanonicalizing(['세팅 A', '세팅 C'], collect($byAsg->json())->pluck('title')->all());
    }

    public function test_calendar_page_renders_search_drawer(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $this->actingAs($user)->get('/calendar')->assertOk()
            ->assertSee('id="calSearchDrawer"', false)
            ->assertSee('csdToggleCat', false)
            ->assertSee('agsToggleAssignee', false)
            ->assertSee('복수 선택');
    }

    public function test_search_filters_by_category_and_assignee(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $assignee = Assignee::create(['name' => '이수호']);
        $gold = $this->makeSchedule(['title' => '세팅 A', 'color' => 'gold']);
        $teal = $this->makeSchedule(['title' => '세팅 B', 'color' => 'teal']);
        $gold->assignees()->attach($assignee->id);

        // 카테고리(색상) 필터
        $byColor = $this->actingAs($user)->getJson('/api/events/search?q=세팅&color=teal');
        $this->assertSame(['세팅 B'], collect($byColor->json())->pluck('title')->all());

        // 담당자 필터 + 결과에 담당자 이름 포함
        $byAssignee = $this->actingAs($user)->getJson('/api/events/search?q=세팅&assignee_id='.$assignee->id);
        $this->assertSame(['세팅 A'], collect($byAssignee->json())->pluck('title')->all());
        $this->assertSame(['이수호'], $byAssignee->json()[0]['assignee_names']);

        // 검색어 없이 필터만으로도 조회 가능
        $onlyFilter = $this->actingAs($user)->getJson('/api/events/search?q=&color=gold');
        $this->assertSame(['세팅 A'], collect($onlyFilter->json())->pluck('title')->all());

        // 검색어도 필터도 없으면 빈 결과
        $this->assertCount(0, $this->actingAs($user)->getJson('/api/events/search?q=')->json());
    }

    public function test_calendar_page_renders_search_filter_chips(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $this->actingAs($user)->get('/calendar')->assertOk()
            ->assertSee('ags-filter-row', false)
            ->assertSee('agsToggleColor', false)
            ->assertSee('agsToggleAssignee', false)
            ->assertSee('카테고리 전체')
            ->assertSee('담당자 전체');
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

    /** 검색어를 URL 인코딩해 호출 — 한글·기호가 환경에 따라 깨지지 않도록 */
    private function searchTitles(User $user, string $q): array
    {
        return collect($this->actingAs($user)->getJson('/api/events/search?'.http_build_query(['q' => $q]))->assertOk()->json())
            ->pluck('title')->sort()->values()->all();
    }

    public function test_search_matches_content_real_name_and_remote_fields(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $this->makeSchedule(['title' => '설명 일정', 'description' => '조명 추가 설치 요청']);
        $this->makeSchedule(['title' => '특이사항 일정', 'special_note' => '주차 불가 건물']);
        $this->makeSchedule(['title' => '전달사항 일정', 'handover_note' => '삼각대 챙기기']);
        $this->makeSchedule(['title' => '실명 일정', 'client_name' => '닉네임', 'request_data' => ['name' => '홍길동', 'nickname' => '닉네임', 'phone' => '010-1234-5678']]);
        $this->makeSchedule(['title' => '의뢰 내용 일정', 'request_data' => ['req_detail' => '캡처보드 교체', 'special' => '반려묘 있음']]);
        $this->makeSchedule(['title' => '원격 일정', 'color' => 'teal', 'remote_data' => ['mode' => 'remote', 'name' => '감자튀김', 'content' => 'OBS 장면 전환 오류']]);
        $this->makeSchedule(['title' => '무관한 일정']);

        $this->assertSame(['설명 일정'], $this->searchTitles($user, '조명 추가'));
        $this->assertSame(['특이사항 일정'], $this->searchTitles($user, '주차 불가'));
        $this->assertSame(['전달사항 일정'], $this->searchTitles($user, '삼각대'));
        $this->assertSame(['실명 일정'], $this->searchTitles($user, '홍길동'));
        $this->assertSame(['의뢰 내용 일정'], $this->searchTitles($user, '캡처보드'));
        $this->assertSame(['의뢰 내용 일정'], $this->searchTitles($user, '반려묘'));
        $this->assertSame(['원격 일정'], $this->searchTitles($user, '감자튀김'));
        $this->assertSame(['원격 일정'], $this->searchTitles($user, '장면 전환'));
    }

    public function test_search_matches_phone_with_or_without_hyphens_for_pii_viewers_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);   // 연락처 조회 권한 있음
        $member = User::factory()->create(['role' => 'member']); // 팀 권한 없음 → 연락처 마스킹 대상
        $this->makeSchedule(['title' => '전화 일정', 'request_data' => ['name' => '홍길동', 'phone' => '010-1234-5678']]);
        $this->makeSchedule(['title' => '다른 일정', 'request_data' => ['phone' => '010-9999-0000']]);

        $this->assertSame(['전화 일정'], $this->searchTitles($admin, '010-1234-5678'));
        $this->assertSame(['전화 일정'], $this->searchTitles($admin, '01012345678'));
        $this->assertSame(['전화 일정'], $this->searchTitles($admin, '1234-5678'));
        $this->assertSame(['전화 일정'], $this->searchTitles($admin, '12345678'));

        // 연락처 조회 권한이 없으면 전화번호로는 찾을 수 없다 (이름으로는 가능)
        $this->assertSame([], $this->searchTitles($member, '01012345678'));
        $this->assertSame([], $this->searchTitles($member, '1234-5678'));
        $this->assertSame(['전화 일정'], $this->searchTitles($member, '홍길동'));
    }

    public function test_search_matches_linked_client_current_name_and_phone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);
        $client = Client::create(['name' => '김철수', 'nickname' => '철수TV', 'phone' => '010-5555-7777', 'grade' => 'normal']);
        $this->makeSchedule(['title' => '연결 일정', 'color' => 'green', 'request_data' => ['client_id' => $client->id, 'nickname' => '', 'name' => '', 'phone' => '']]);
        $this->makeSchedule(['title' => '문자열 ID 일정', 'color' => 'green', 'request_data' => ['client_id' => (string) $client->id]]);
        $this->makeSchedule(['title' => '무관한 일정']);

        $this->assertSame(['문자열 ID 일정', '연결 일정'], $this->searchTitles($admin, '김철수'));
        $this->assertSame(['문자열 ID 일정', '연결 일정'], $this->searchTitles($admin, '철수TV'));
        $this->assertSame(['문자열 ID 일정', '연결 일정'], $this->searchTitles($admin, '55557777'));
        $this->assertSame(['문자열 ID 일정', '연결 일정'], $this->searchTitles($member, '철수TV'));
        $this->assertSame([], $this->searchTitles($member, '55557777'));
    }

    public function test_expanded_search_still_hides_other_users_private_events_and_blocks_guests(): void
    {
        $owner = User::factory()->create(['role' => 'member']);
        $other = User::factory()->create(['role' => 'member']);
        $guest = User::factory()->create(['role' => 'guest']);
        $this->makeSchedule(['title' => '비공개', 'is_private' => true, 'created_by' => $owner->id, 'description' => '극비 회의 안건']);

        $this->assertSame(['비공개'], $this->searchTitles($owner, '극비 회의'));
        $this->assertSame([], $this->searchTitles($other, '극비 회의'));
        $this->assertSame([], $this->searchTitles($guest, '극비 회의'));
    }
}
