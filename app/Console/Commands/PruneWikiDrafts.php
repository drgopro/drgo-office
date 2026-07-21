<?php

namespace App\Console\Commands;

use App\Models\Wiki;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('wiki:prune-drafts {--keep=7 : 보관 일수 (마지막 수정 기준)}')]
#[Description('오래된 위키 임시저장 글 삭제 — 첨부파일·썸네일 포함')]
class PruneWikiDrafts extends Command
{
    public function handle(): int
    {
        $keepDays = max(1, (int) $this->option('keep'));
        $stale = Wiki::where('is_draft', true)
            ->where('updated_at', '<', now()->subDays($keepDays))
            ->get();

        foreach ($stale as $draft) {
            foreach ($draft->attachments as $attachment) {
                Storage::delete($attachment->file_path);
                Storage::delete('thumbs/'.md5($attachment->file_path).'.webp');
            }
            $draft->delete(); // 첨부 행은 FK cascade로 함께 삭제
            $this->line("임시저장 삭제: #{$draft->id} {$draft->title} (마지막 수정 {$draft->updated_at->format('Y-m-d')})");
        }

        $this->info("보관 기한({$keepDays}일) 지난 임시저장 {$stale->count()}건 정리");

        return self::SUCCESS;
    }
}
