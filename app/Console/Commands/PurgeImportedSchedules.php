<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('calendar:purge-imported {--force : 실제로 삭제 (기본은 조회만)}')]
#[Description('iCal 가져오기로 등록된 일정(import_uid 보유)을 전부 완전 삭제 — 파일 재가져오기 전 초기화용')]
class PurgeImportedSchedules extends Command
{
    public function handle(): int
    {
        // 소프트 삭제분 포함 — 남겨두면 UID 중복 방지에 걸려 재가져오기가 스킵됨
        $targets = Schedule::withTrashed()->whereNotNull('import_uid');

        $total = (clone $targets)->count();
        if ($total === 0) {
            $this->info('가져오기로 등록된 일정이 없습니다.');

            return self::SUCCESS;
        }

        $edited = (clone $targets)->whereColumn('updated_at', '>', 'created_at')->count();
        $trashed = (clone $targets)->whereNotNull('deleted_at')->count();

        $this->warn("가져오기로 등록된 일정: {$total}건 (휴지통 {$trashed}건 포함)");
        if ($edited > 0) {
            $this->warn("⚠ 이 중 {$edited}건은 가져온 뒤 수정된 흔적이 있습니다 — 수정 내용도 함께 삭제됩니다.");
        }
        $this->table(
            ['유형', '건수'],
            (clone $targets)->selectRaw('color, count(*) as cnt')->groupBy('color')->pluck('cnt', 'color')
                ->map(fn ($n, $c) => [$c, $n])->values()->all()
        );

        if (! $this->option('force')) {
            $this->line('삭제하려면 --force 옵션을 붙여 다시 실행하세요. (완전 삭제 — 복구 불가, 파일 재가져오기로만 복원)');

            return self::SUCCESS;
        }

        // 완전 삭제 — 관련 테이블(담당자 연결/변경이력/첨부/배송)은 FK cascade로 함께 정리
        $deleted = 0;
        (clone $targets)->chunkById(200, function ($chunk) use (&$deleted) {
            foreach ($chunk as $s) {
                $s->forceDelete();
                $deleted++;
            }
        });

        $this->info("{$deleted}건 완전 삭제 완료. 이제 iCal 가져오기를 다시 실행하면 전부 새로 등록됩니다.");

        return self::SUCCESS;
    }
}
