<?php

namespace App\Console\Commands;

use App\Models\ClientDocument;
use App\Models\FeedbackAttachment;
use App\Models\ProjectDocument;
use App\Models\ScheduleAttachment;
use App\Models\TodoAttachment;
use App\Models\WikiAttachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('attachments:prune-orphans {--dry-run : 삭제 없이 대상만 출력} {--age=2 : 디스크 고아 파일 최소 경과일 (업로드 진행 중 파일 보호)}')]
#[Description('부모 레코드가 영구 삭제된 첨부 행·파일과 DB에 없는 디스크 고아 파일 정리 (소프트델리트 부모의 첨부는 복원 대비 유지)')]
class PruneOrphanAttachments extends Command
{
    /** @var array<class-string, array{fk: string, parent: string}> 첨부 모델 → 부모 테이블 매핑 */
    private const ATTACHMENT_PARENTS = [
        ClientDocument::class => ['fk' => 'client_id', 'parent' => 'clients'],
        ProjectDocument::class => ['fk' => 'project_id', 'parent' => 'projects'],
        ScheduleAttachment::class => ['fk' => 'schedule_id', 'parent' => 'schedules'],
        TodoAttachment::class => ['fk' => 'todo_id', 'parent' => 'todos'],
        WikiAttachment::class => ['fk' => 'wiki_id', 'parent' => 'wikis'],
        FeedbackAttachment::class => ['fk' => 'feedback_post_id', 'parent' => 'feedback_posts'],
    ];

    /** @var list<string> 디스크 스캔 대상 디렉터리 (업로드 경로와 일치해야 함) */
    private const SCAN_DIRS = ['clients', 'projects', 'schedules', 'todos', 'feedback', 'wiki'];

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $rows = $this->pruneOrphanRows();
        $pending = $this->pruneAbandonedPendingUploads();
        [$files, $thumbs] = $this->pruneOrphanFiles(max(0, (int) $this->option('age')));

        $mode = $this->dryRun ? '[dry-run] ' : '';
        $this->info("{$mode}고아 첨부 행 {$rows}건 · 방치된 임시 업로드 {$pending}건 · 디스크 고아 파일 {$files}개 · 무효 썸네일 {$thumbs}개 정리");

        return self::SUCCESS;
    }

    /** 부모 레코드가 테이블에서 사라진(영구 삭제) 첨부 행 + 파일 삭제. 소프트델리트 부모는 유지. */
    private function pruneOrphanRows(): int
    {
        $count = 0;

        foreach (self::ATTACHMENT_PARENTS as $model => $map) {
            $orphans = $model::query()
                ->whereNotNull($map['fk'])
                ->whereNotExists(function ($q) use ($model, $map) {
                    $q->select(DB::raw(1))
                        ->from($map['parent'])
                        ->whereColumn("{$map['parent']}.id", (new $model)->getTable().".{$map['fk']}");
                })
                ->get();

            foreach ($orphans as $orphan) {
                $this->line(($this->dryRun ? '[dry-run] ' : '').'고아 행: '.class_basename($model)." #{$orphan->id} ({$orphan->file_path})");
                if (! $this->dryRun) {
                    $this->deleteWithThumb($orphan->file_path);
                    $orphan->delete();
                }
                $count++;
            }
        }

        return $count;
    }

    /** 게시물 저장 전 업로드 후 방치된 위키 임시 첨부 (wiki_id null, 7일 경과) */
    private function pruneAbandonedPendingUploads(): int
    {
        $stale = WikiAttachment::whereNull('wiki_id')->where('created_at', '<', now()->subDays(7))->get();

        foreach ($stale as $row) {
            $this->line(($this->dryRun ? '[dry-run] ' : '')."임시 업로드: WikiAttachment #{$row->id} ({$row->file_path})");
            if (! $this->dryRun) {
                $this->deleteWithThumb($row->file_path);
                $row->delete();
            }
        }

        return $stale->count();
    }

    /**
     * DB 어디에서도 참조되지 않는 디스크 파일 삭제 (FK cascade로 행만 지워진 경우 등).
     *
     * @return array{0: int, 1: int} [삭제 파일 수, 삭제 썸네일 수]
     */
    private function pruneOrphanFiles(int $minAgeDays): array
    {
        $referenced = [];
        foreach (self::ATTACHMENT_PARENTS as $model => $map) {
            foreach ($model::query()->pluck('file_path') as $path) {
                $referenced[$path] = true;
            }
        }

        $cutoff = now()->subDays($minAgeDays)->getTimestamp();
        $files = 0;

        foreach (self::SCAN_DIRS as $dir) {
            foreach (Storage::allFiles($dir) as $path) {
                if (isset($referenced[$path]) || Storage::lastModified($path) >= $cutoff) {
                    continue;
                }
                $this->line(($this->dryRun ? '[dry-run] ' : '')."고아 파일: {$path}");
                if (! $this->dryRun) {
                    $this->deleteWithThumb($path);
                }
                $files++;
            }
        }

        // 썸네일: 참조 중인 원본의 md5 이름만 유효 — 나머지는 삭제 (필요 시 자동 재생성됨)
        $validThumbs = [];
        foreach (array_keys($referenced) as $path) {
            $validThumbs['thumbs/'.md5($path).'.webp'] = true;
        }
        $thumbs = 0;
        foreach (Storage::files('thumbs') as $thumb) {
            if (! isset($validThumbs[$thumb])) {
                if (! $this->dryRun) {
                    Storage::delete($thumb);
                }
                $thumbs++;
            }
        }

        return [$files, $thumbs];
    }

    private function deleteWithThumb(string $path): void
    {
        Storage::delete($path);
        Storage::delete('thumbs/'.md5($path).'.webp');
    }
}
