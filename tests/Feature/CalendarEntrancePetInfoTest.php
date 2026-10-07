<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * 일정 주소 영역의 현장 출입 정보 — 공동현관 정보·반려동물 여부 수기 입력.
 * 공동현관 정보(출입 비밀번호 등)는 상세 주소와 같은 등급으로, clients.pii
 * 권한이 없으면 마스킹된다.
 */
class CalendarEntrancePetInfoTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        $team = Team::create(['name' => '팀'.md5(implode($permissions)), 'slug' => 'perm-'.substr(md5(implode($permissions)), 0, 8), 'permissions' => $permissions]);

        return User::factory()->create(['role' => 'member', 'team_id' => $team->id]);
    }

    public function test_entrance_and_pet_info_save_and_update(): void
    {
        $master = User::factory()->create(['role' => 'master']);

        $id = $this->actingAs($master)->postJson('/api/events', [
            'title' => '세팅 방문', 'start_date' => '2026-07-22', 'end_date' => '2026-07-22',
            'is_all_day' => true, 'color' => 'gold',
            'address' => '서울시 동작구 장승배기로 142', 'location' => '서울시 동작구 장승배기로 142 101동 202호',
            'entrance_info' => '공동현관 #1234* 후 호출', 'pet_info' => '강아지 1마리 (소형견)',
        ])->assertCreated()->json('id');

        $s = Schedule::find($id);
        $this->assertSame('공동현관 #1234* 후 호출', $s->entrance_info);
        $this->assertSame('강아지 1마리 (소형견)', $s->pet_info);

        // 수정·비우기
        $this->actingAs($master)->patchJson("/api/events/{$id}", [
            'entrance_info' => '경비실 호출', 'pet_info' => null,
        ])->assertOk();
        $s = $s->fresh();
        $this->assertSame('경비실 호출', $s->entrance_info);
        $this->assertNull($s->pet_info);
    }

    public function test_entrance_info_masked_without_pii_permission(): void
    {
        $master = User::factory()->create(['role' => 'master']);
        $schedule = Schedule::create([
            'title' => '출입정보 마스킹', 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'is_all_day' => true, 'color' => 'gold',
            'address' => '서울시 동작구 장승배기로 142',
            'entrance_info' => '#5678', 'pet_info' => '고양이 2마리',
            'created_by' => $master->id,
        ]);
        $range = '?start='.now()->subDay()->toDateString().'&end='.now()->addDay()->toDateString();

        // 권한 없음 — 공동현관 정보는 감춤, 반려동물 여부는 유지
        $user = $this->userWith(['calendar.view', 'clients.view']);
        $ev = collect($this->actingAs($user)->getJson('/api/events'.$range)->assertOk()->json())->firstWhere('id', $schedule->id);
        $this->assertSame('', $ev['entrance_info']);
        $this->assertSame('고양이 2마리', $ev['pet_info']);

        // 권한 있음 — 그대로
        $piiUser = $this->userWith(['calendar.view', 'clients.view', 'clients.pii']);
        $ev2 = collect($this->actingAs($piiUser)->getJson('/api/events'.$range)->assertOk()->json())->firstWhere('id', $schedule->id);
        $this->assertSame('#5678', $ev2['entrance_info']);
    }

    public function test_export_and_import_round_trip_keeps_fields(): void
    {
        $master = User::factory()->create(['role' => 'master']);
        Schedule::create([
            'title' => '백업 왕복', 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'is_all_day' => true, 'color' => 'gold',
            'entrance_info' => '공동현관 1111', 'pet_info' => '없음', 'created_by' => $master->id,
        ]);

        $export = $this->actingAs($master)->get('/api/events/export/json')->assertOk()->json();
        $row = collect($export['events'])->firstWhere('title', '백업 왕복');
        $this->assertSame('공동현관 1111', $row['entrance_info']);
        $this->assertSame('없음', $row['pet_info']);

        Schedule::query()->forceDelete();
        $file = UploadedFile::fake()->createWithContent('backup.json', json_encode($export));
        $this->actingAs($master)->post('/api/events/import/json', ['file' => $file])->assertOk();
        $restored = Schedule::where('title', '백업 왕복')->firstOrFail();
        $this->assertSame('공동현관 1111', $restored->entrance_info);
        $this->assertSame('없음', $restored->pet_info);
    }

    public function test_calendar_page_renders_input_fields_and_summary_markers(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)->get('/calendar')->assertOk()
            ->assertSee('id="modalEntranceInfo"', false)
            ->assertSee('id="modalPetInfo"', false)
            ->assertSee('공동현관 정보 (출입 방법·비밀번호 등)', false)
            ->assertSee('반려동물 여부', false)
            // 저장 payload·요약 뷰 표시
            ->assertSee('entrance_info:document.getElementById', false)
            ->assertSee("entranceTxt=_val('modalEntranceInfo')", false);
    }
}
