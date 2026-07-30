<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * 운영 DB에 잘못 시드된 스모크 테스트 데이터 제거 (smoke:run의 config:cache 우회 버그 수습용).
 * SmokeSeeder가 만드는 고정 마커 데이터만 정확히 지운다 — 몇 번을 실행해도 안전(멱등).
 */
class CleanupSmokeData extends Command
{
    protected $signature = 'smoke:cleanup';

    protected $description = '운영 DB에 유입된 스모크 테스트 데이터(smoke 계정 등) 제거';

    public function handle(): int
    {
        $removed = [];

        $removed['할 일'] = Todo::where('title', '스모크 할 일')->delete();
        $removed['일정'] = Schedule::withTrashed()->where('title', 'like', '스모크%')->forceDelete();

        $project = Project::where('name', '스모크 프로젝트')->first();
        if ($project) {
            $project->delete();
            $removed['프로젝트'] = 1;
        }
        $removed['의뢰자'] = Client::where('nickname', '스모크의뢰자')->delete();

        // 핵심 — master 권한으로 생성된 smoke 계정 제거
        $removed['smoke 계정'] = User::where('username', 'smoke')->delete();

        foreach ($removed as $label => $cnt) {
            $this->line("  {$label}: ".(int) $cnt.'건 삭제');
        }
        $this->info('정리 완료 — smoke 계정이 0건이면 이미 제거된 상태입니다.');

        return self::SUCCESS;
    }
}
