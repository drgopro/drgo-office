<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DoctorAttachments extends Command
{
    protected $signature = 'attachments:doctor {keyword : 일정 제목 검색어}';

    protected $description = '일정 첨부 이미지 상태 점검 — 파일 존재/크기 일치/디코딩 가능 여부와 썸네일 캐시 상태 출력';

    public function handle(): int
    {
        $keyword = (string) $this->argument('keyword');
        $schedules = Schedule::withTrashed()
            ->where('title', 'like', "%{$keyword}%")
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        if ($schedules->isEmpty()) {
            $this->warn("'{$keyword}' 제목의 일정을 찾지 못했습니다.");

            return self::FAILURE;
        }

        foreach ($schedules as $schedule) {
            $this->info("── 일정 #{$schedule->id} {$schedule->title} ({$schedule->start_date?->toDateString()})");
            if ($schedule->attachments->isEmpty()) {
                $this->line('   첨부 없음');

                continue;
            }
            foreach ($schedule->attachments as $a) {
                $exists = Storage::exists($a->file_path);
                $diskSize = $exists ? Storage::size($a->file_path) : 0;
                $sizeMatch = ((int) $a->file_size === $diskSize) ? 'OK' : "불일치(DB {$a->file_size})";
                $decode = 'n/a';
                if ($exists && str_starts_with((string) $a->mime_type, 'image/')) {
                    $img = @imagecreatefromstring(Storage::get($a->file_path));
                    $decode = $img !== false ? 'OK' : '손상(디코딩 실패)';
                    if ($img !== false) {
                        imagedestroy($img);
                    }
                }
                $thumbPath = 'thumbs/'.md5($a->file_path).'.webp';
                $thumb = Storage::exists($thumbPath) ? Storage::size($thumbPath).'B' : '없음';
                $this->line(sprintf(
                    '   #%d %s | mime=%s | 파일=%s | 크기=%s(%s) | 원본디코딩=%s | 썸네일캐시=%s',
                    $a->id, $a->file_name, $a->mime_type,
                    $exists ? '있음' : '없음(유실)', $diskSize, $sizeMatch, $decode, $thumb
                ));
            }
        }
        $this->line('');
        $this->line('※ 원본디코딩=손상 → 업로드 중 파일이 잘린 것으로 복구 불가, 해당 첨부를 다시 업로드해야 합니다.');
        $this->line('※ 썸네일만 문제면: php artisan tinker --execute="Storage::deleteDirectory(\'thumbs\');" 후 재접속');

        return self::SUCCESS;
    }
}
