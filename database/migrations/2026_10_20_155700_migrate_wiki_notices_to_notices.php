<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 위키의 공지사항(type=notice) 게시물을 새 공지사항 게시판으로 이관.
 * - notices.legacy_wiki_id 로 원본을 기억해 기존 /wiki/{id} 링크를 새 공지로 리다이렉트
 * - 읽음 기록(wiki_reads)도 함께 복사
 * - 본문 속 첨부 URL(/wiki-files/...)은 위키 첨부 라우트가 계속 서빙하므로 본문은 그대로 복사
 * - 원본 위키 행은 리다이렉트 판정을 위해 남겨두고, 위키 목록에서는 type=notice 섹션 제거로 숨김
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('notices', 'legacy_wiki_id')) {
            Schema::table('notices', function (Blueprint $table) {
                $table->unsignedBigInteger('legacy_wiki_id')->nullable()->unique()->after('sort_order');
            });
        }

        $wikis = DB::table('wikis')->where('type', 'notice')->orderBy('id')->get();
        foreach ($wikis as $w) {
            $exists = DB::table('notices')->where('legacy_wiki_id', $w->id)->exists();
            if ($exists) {
                continue;
            }
            $noticeId = DB::table('notices')->insertGetId([
                'title' => $w->title,
                'category_id' => null, // 공지 카테고리 체계는 새로 — 필요 시 게시판에서 분류
                'content' => $w->content,
                'is_pinned' => (bool) $w->is_pinned,
                'pinned_until' => null,
                'is_draft' => (bool) $w->is_draft,
                'allowed_team_ids' => $w->allowed_team_ids,
                'sort_order' => $w->sort_order ?? 0,
                'legacy_wiki_id' => $w->id,
                'created_by' => $w->created_by,
                'updated_by' => $w->updated_by,
                'created_at' => $w->created_at,
                'updated_at' => $w->updated_at,
            ]);

            if (Schema::hasTable('wiki_reads')) {
                $reads = DB::table('wiki_reads')->where('wiki_id', $w->id)->get();
                foreach ($reads as $r) {
                    DB::table('notice_reads')->insert([
                        'notice_id' => $noticeId,
                        'user_id' => $r->user_id,
                        'read_at' => $r->read_at,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('notices')->whereNotNull('legacy_wiki_id')->delete();
        Schema::table('notices', function (Blueprint $table) {
            $table->dropColumn('legacy_wiki_id');
        });
    }
};
