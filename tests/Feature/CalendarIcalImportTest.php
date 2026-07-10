<?php

namespace Tests\Feature;

use App\Models\CalendarCategory;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CalendarIcalImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'master']);
    }

    private function icsFile(string $body): File
    {
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//TickTick Backup//Test//KO\r\n"
            .$body."END:VCALENDAR\r\n";

        return UploadedFile::fake()->createWithContent('backup.ics', $ics);
    }

    private function vevent(string $uid, string $summary, string $categories, string $extra = ''): string
    {
        return "BEGIN:VEVENT\r\nUID:{$uid}@ticktick-backup\r\nDTSTAMP:20260708T103708Z\r\n"
            ."DTSTART;TZID=Asia/Seoul:20260708T180000\r\nDTEND;TZID=Asia/Seoul:20260708T190000\r\n"
            ."SUMMARY:{$summary}\r\nCATEGORIES:{$categories}\r\n{$extra}STATUS:CONFIRMED\r\nEND:VEVENT\r\n";
    }

    private function import(File $file, bool $dry = false)
    {
        return $this->actingAs($this->user)->post('/api/events/import/ical',
            ['file' => $file] + ($dry ? ['dry' => '1'] : []), ['Accept' => 'application/json']);
    }

    public function test_categories_map_to_app_categories(): void
    {
        $file = $this->icsFile(
            $this->vevent('u1', '✓ 하유필 부평', '의뢰자\\,개인의뢰')
            .$this->vevent('u2', '✓ 원격 세팅', '원격\\,원격')
            .$this->vevent('u3', '✓ 연차', '일반\\,휴가')
            .$this->vevent('u4', '✓ 장비 회수', '렌탈&방송룸월대여\\,장비렌탈')
            .$this->vevent('u5', '✓ 손님 방문', '의뢰자\\,내방')
            .$this->vevent('u6', '✓ 주간 회의', '일반')
        );

        $this->import($file)->assertOk();

        $this->assertSame('gold', Schedule::where('import_uid', 'u1@ticktick-backup')->first()->color);
        $this->assertSame('teal', Schedule::where('import_uid', 'u2@ticktick-backup')->first()->color);
        $this->assertSame('red', Schedule::where('import_uid', 'u3@ticktick-backup')->first()->color);
        $this->assertSame('purple', Schedule::where('import_uid', 'u5@ticktick-backup')->first()->color);
        $this->assertSame('blue', Schedule::where('import_uid', 'u6@ticktick-backup')->first()->color);

        // 렌탈 → '렌탈' 커스텀 카테고리 자동 생성 (방송룸(teal)보다 우선)
        $rental = Schedule::where('import_uid', 'u4@ticktick-backup')->first();
        $cat = CalendarCategory::where('label', '렌탈')->first();
        $this->assertNotNull($cat);
        $this->assertSame($cat->key, $rental->color);
    }

    public function test_holiday_skipped_and_birthday_is_red(): void
    {
        $file = $this->icsFile(
            $this->vevent('h1', '신정', '일반\\,공휴일')
            .$this->vevent('b1', '✓ 어머니 생신', '일반\\,생일')
        );

        $res = $this->import($file);

        $res->assertOk()->assertJsonPath('skipped_holiday', 1);
        $this->assertNull(Schedule::where('import_uid', 'h1@ticktick-backup')->first());
        $this->assertSame('red', Schedule::where('import_uid', 'b1@ticktick-backup')->first()->color);
    }

    public function test_checkmark_stripped_and_completed_set(): void
    {
        $file = $this->icsFile(
            $this->vevent('c1', '✓ 완료된 일정', '일반')
            .$this->vevent('c2', '미완료 일정', '일반')
        );

        $this->import($file)->assertOk();

        $done = Schedule::where('import_uid', 'c1@ticktick-backup')->first();
        $this->assertSame('완료된 일정', $done->title);
        $this->assertNotNull($done->completed_at);

        $open = Schedule::where('import_uid', 'c2@ticktick-backup')->first();
        $this->assertSame('미완료 일정', $open->title);
        $this->assertNull($open->completed_at);
    }

    public function test_dry_run_saves_nothing_and_reports_summary(): void
    {
        $file = $this->icsFile(
            $this->vevent('d1', '✓ 디자인 작업', '디자인\\,디자인')
            .$this->vevent('d2', '신정', '일반\\,공휴일')
        );

        $res = $this->import($file, dry: true);

        $res->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('skipped_holiday', 1)
            ->assertJsonPath('by_category.디자인', 1)
            ->assertJsonPath('will_create_categories.0', '디자인');
        $this->assertSame(0, Schedule::count());
        $this->assertNull(CalendarCategory::where('label', '디자인')->first(), 'dry-run은 카테고리도 생성하지 않음');
    }

    public function test_reimport_skips_duplicates_by_uid(): void
    {
        $file1 = $this->icsFile($this->vevent('r1', '✓ 일정', '일반'));
        $this->import($file1)->assertOk();

        $file2 = $this->icsFile($this->vevent('r1', '✓ 일정', '일반'));
        $res = $this->import($file2);

        $res->assertOk()->assertJsonPath('duplicates', 1)->assertJsonPath('count', 0);
        $this->assertSame(1, Schedule::count());
    }

    public function test_folded_description_and_allday_range(): void
    {
        $body = "BEGIN:VEVENT\r\nUID:f1@ticktick-backup\r\nDTSTAMP:20260708T103708Z\r\n"
            ."DTSTART;VALUE=DATE:20260706\r\nDTEND;VALUE=DATE:20260708\r\n"
            ."SUMMARY:✓ 접힌 설명\r\nDESCRIPTION:포인트랑 현장 설명 진행후 돌아가는거 확인\r\n 하고 퇴각\r\n"
            ."CATEGORIES:일반\r\nEND:VEVENT\r\n";
        $this->import($this->icsFile($body))->assertOk();

        $s = Schedule::where('import_uid', 'f1@ticktick-backup')->first();
        $this->assertTrue($s->is_all_day);
        $this->assertSame('2026-07-06', $s->start_date->format('Y-m-d'));
        $this->assertSame('2026-07-07', $s->end_date->format('Y-m-d'), 'iCal 종일 DTEND는 exclusive → -1일');
        $this->assertSame('포인트랑 현장 설명 진행후 돌아가는거 확인하고 퇴각', $s->description);
    }

    public function test_description_goes_to_visible_field_per_category(): void
    {
        $file = $this->icsFile(
            $this->vevent('v1', '✓ 방문 세팅', '의뢰자\\,개인의뢰', "DESCRIPTION:방문 상세 내용\r\n")
            .$this->vevent('v2', '✓ 원격 세팅', '원격\\,원격', "DESCRIPTION:원격 상세 내용\r\n")
            .$this->vevent('v3', '✓ 회의', '일반', "DESCRIPTION:회의 안건\r\n")
        );

        $this->import($file)->assertOk();

        // gold: 편집 폼에 보이는 요청상세(gold_data.req_detail)로
        $gold = Schedule::where('import_uid', 'v1@ticktick-backup')->first();
        $this->assertSame('방문 상세 내용', $gold->gold_data['req_detail']);
        $this->assertNull($gold->description);

        // teal: 상세설명(teal_data.desc)로
        $teal = Schedule::where('import_uid', 'v2@ticktick-backup')->first();
        $this->assertSame('원격 상세 내용', $teal->teal_data['desc']);
        $this->assertNull($teal->description);

        // 공통 유형: description 그대로
        $blue = Schedule::where('import_uid', 'v3@ticktick-backup')->first();
        $this->assertSame('회의 안건', $blue->description);
    }

    public function test_reimport_repairs_content_hidden_in_description(): void
    {
        // 이전 버전 가져오기로 생긴 상태: gold인데 내용이 description에만 있음
        Schedule::create([
            'title' => '방문 세팅', 'start_date' => '2026-07-08', 'end_date' => '2026-07-08',
            'is_all_day' => true, 'color' => 'gold', 'description' => '숨어있던 내용',
            'import_uid' => 'rp1@ticktick-backup',
        ]);

        $file = $this->icsFile($this->vevent('rp1', '✓ 방문 세팅', '의뢰자\\,개인의뢰', "DESCRIPTION:숨어있던 내용\r\n"));

        // dry-run: 치유 대상으로 보고만, 변경 없음
        $dry = $this->import($file, dry: true);
        $dry->assertOk()->assertJsonPath('repaired', 1);
        $this->assertSame('숨어있던 내용', Schedule::first()->description);

        // 실제 실행: description → gold_data.req_detail 이동
        $res = $this->import($this->icsFile($this->vevent('rp1', '✓ 방문 세팅', '의뢰자\\,개인의뢰', "DESCRIPTION:숨어있던 내용\r\n")));
        $res->assertOk()->assertJsonPath('repaired', 1)->assertJsonPath('duplicates', 1);

        $row = Schedule::first();
        $this->assertSame('숨어있던 내용', $row->gold_data['req_detail']);
        $this->assertNull($row->description);
        $this->assertSame(1, Schedule::count(), '중복 생성 없음');
    }

    public function test_until_cutoff_skips_events_starting_after(): void
    {
        // vevent 기본 시작일은 2026-07-08 — until=2026-06-30이면 제외되어야 함
        $body = $this->vevent('cut1', '✓ 6월 일정', '일반');
        $body = str_replace('20260708T180000', '20260615T180000', str_replace('20260708T190000', '20260615T190000', $body));
        $file = $this->icsFile($body.$this->vevent('cut2', '✓ 7월 일정', '일반'));

        $res = $this->actingAs($this->user)->post('/api/events/import/ical',
            ['file' => $file, 'until' => '2026-06-30'], ['Accept' => 'application/json']);

        $res->assertOk()->assertJsonPath('skipped_after', 1)->assertJsonPath('count', 1);
        $this->assertNotNull(Schedule::where('import_uid', 'cut1@ticktick-backup')->first());
        $this->assertNull(Schedule::where('import_uid', 'cut2@ticktick-backup')->first());
    }

    public function test_studio_matches_default_combined_label(): void
    {
        // 기본 카테고리 green 라벨 '촬영/스튜디오'에 부분 일치 → 새 카테고리 생성 없이 green 사용
        $file = $this->icsFile($this->vevent('s1', '✓ 스튜디오 세팅', '의뢰자\\,스튜디오'));
        $this->import($file)->assertOk();

        $this->assertSame('green', Schedule::where('import_uid', 's1@ticktick-backup')->first()->color);
        $this->assertNull(CalendarCategory::where('label', '스튜디오')->first());
    }
}
