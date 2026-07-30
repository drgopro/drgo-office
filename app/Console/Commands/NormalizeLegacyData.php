<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Schedule;
use App\Support\Normalize;
use Illuminate\Console\Command;

/**
 * 레거시 표기 혼재 일괄 정리 — 플랫폼(SOOP/아프리카→숲 등)·경력(신규→처음).
 * 기본은 미리보기(dry-run) — 실제 반영은 --apply. 알 수 없는 값은 절대 바꾸지 않는다.
 * 결과는 storage/logs/normalize.log 에도 기록 (/admin/normalize-log 로 웹 확인).
 */
class NormalizeLegacyData extends Command
{
    protected $signature = 'data:normalize {--apply : 실제로 DB에 반영 (없으면 미리보기만)}';

    protected $description = '플랫폼·경력 표기 혼재 정리 (기본 dry-run)';

    private string $log = '';

    private function say(string $line): void
    {
        $this->line($line);
        $this->log .= $line."\n";
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->log = '── data:normalize '.now()->format('Y-m-d H:i:s').($apply ? ' [반영]' : ' [미리보기]')." ──\n";

        try {
            $this->normalizeClients($apply);
            $this->normalizeSchedules($apply);
            $this->say($apply ? "\n반영 완료." : "\n미리보기입니다 — 반영하려면: php artisan data:normalize --apply");
        } finally {
            @file_put_contents(storage_path('logs/normalize.log'), $this->log);
        }

        return self::SUCCESS;
    }

    private function normalizeClients(bool $apply): void
    {
        $platformChanged = 0;
        $careerChanged = 0;
        $samples = [];

        Client::query()->chunkById(200, function ($clients) use ($apply, &$platformChanged, &$careerChanged, &$samples) {
            foreach ($clients as $c) {
                $dirty = false;

                $platforms = $c->platforms ?? [];
                $newPlatforms = array_values(array_unique(array_map(
                    fn ($p) => Normalize::platform((string) $p) ?? $p,
                    $platforms
                )));
                if ($newPlatforms !== $platforms) {
                    $platformChanged++;
                    $dirty = true;
                    if (count($samples) < 10) {
                        $samples[] = "  #{$c->id} {$c->nickname}: [".implode(',', $platforms).'] → ['.implode(',', $newPlatforms).']';
                    }
                    $c->platforms = $newPlatforms;
                }

                $career = (string) ($c->career ?? '');
                $newCareer = $career !== '' ? (Normalize::career($career) ?? $career) : $career;
                if ($newCareer !== $career) {
                    $careerChanged++;
                    $dirty = true;
                    $c->career = $newCareer;
                }

                if ($apply && $dirty) {
                    $c->saveQuietly(); // updated_at·이벤트 없이 표기만 정정
                }
            }
        });

        $this->say("의뢰자: 플랫폼 표기 변경 {$platformChanged}건, 경력 변경 {$careerChanged}건");
        foreach ($samples as $s) {
            $this->say($s);
        }
    }

    private function normalizeSchedules(bool $apply): void
    {
        $changed = 0;
        $samples = [];

        Schedule::withTrashed()->whereNotNull('request_data')->chunkById(200, function ($schedules) use ($apply, &$changed, &$samples) {
            foreach ($schedules as $s) {
                $g = (array) $s->request_data;
                $orig = $g;

                // 플랫폼 — 쉼표 구분 복수 표기는 요소별 정규화 (구조 보존)
                if (! empty($g['platform'])) {
                    $parts = array_map('trim', explode(',', (string) $g['platform']));
                    $normed = array_values(array_unique(array_map(fn ($p) => Normalize::platform($p) ?? $p, $parts)));
                    $g['platform'] = implode(', ', $normed);
                }
                if (! empty($g['career'])) {
                    $g['career'] = Normalize::career((string) $g['career']) ?? $g['career'];
                }

                if ($g !== $orig) {
                    $changed++;
                    if (count($samples) < 10) {
                        $samples[] = "  일정 #{$s->id} {$s->title}: "
                            .($orig['platform'] ?? '').'/'.($orig['career'] ?? '')
                            .' → '.($g['platform'] ?? '').'/'.($g['career'] ?? '');
                    }
                    if ($apply) {
                        $s->request_data = $g;
                        $s->saveQuietly();
                    }
                }
            }
        });

        $this->say("일정(request_data): 표기 변경 {$changed}건");
        foreach ($samples as $s) {
            $this->say($s);
        }
    }
}
