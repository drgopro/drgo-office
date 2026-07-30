<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * UI 스모크 테스트용 최소 데이터 — `php artisan smoke:run`이 빈 sqlite DB에 시드.
 * 아이디 의존 테스트가 있으므로 항상 빈 DB에서 실행한다 (client=1, project=1).
 */
class SmokeSeeder extends Seeder
{
    public function run(): void
    {
        // 안전장치 — 스모크 시드는 임시 sqlite 전용. 운영(mysql 등)에서는 절대 실행 금지.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new \RuntimeException('SmokeSeeder는 sqlite 전용입니다 — 운영 DB에서 실행이 차단되었습니다.');
        }

        $user = User::create([
            'username' => 'smoke',
            'display_name' => '스모크',
            'password' => Hash::make('smoke1234'),
            'role' => 'master',
            'is_active' => true,
        ]);

        $client = Client::create(['nickname' => '스모크의뢰자', 'grade' => 'normal', 'platforms' => ['SOOP']]);
        $project = Project::create([
            'name' => '스모크 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting',
            'client_id' => $client->id, 'client_scale' => 'personal',
            'custom_data' => ['__req_items' => [['t' => '신규·이사 세팅', 'c' => '오디오', 'd' => '마이크 추가 설치', 'qty' => 2]]],
        ]);

        $today = now()->format('Y-m-d');

        // 방문의뢰(gold) — 확정 + 프로젝트 연동 (모달/요약 뷰 검증용)
        Schedule::create([
            'title' => '스모크 방문세팅 (급행)', 'start_date' => $today, 'end_date' => $today,
            'color' => 'gold', 'start_time' => '10:00', 'end_time' => '13:00', 'is_all_day' => false,
            'client_name' => '스모크의뢰자', 'address' => '서울특별시 강남구 역삼동 1', 'sched_opt' => 'confirmed',
            'request_data' => [
                'client_id' => $client->id, 'project_id' => $project->id, 'nickname' => '스모크의뢰자',
                'platform' => 'SOOP', 'career' => '경력', 'estimate_amount' => '1,000,000',
            ],
        ]);
        // 사내업무 + 휴가 다일 바 (컴팩트 뷰 레인 검증용)
        Schedule::create(['title' => '스모크 사내 회의', 'start_date' => $today, 'end_date' => $today, 'color' => 'blue', 'is_all_day' => true, 'sched_opt' => 'target']);
        Schedule::create([
            'title' => '스모크 연차', 'start_date' => $today,
            'end_date' => now()->addDays(2)->format('Y-m-d'), 'color' => 'red', 'is_all_day' => true,
        ]);

        Todo::create([
            'title' => '스모크 할 일', 'priority' => 'medium', 'due_date' => now()->addDays(3),
            'assignee_id' => $user->id, 'created_by' => $user->id,
        ]);
    }
}
