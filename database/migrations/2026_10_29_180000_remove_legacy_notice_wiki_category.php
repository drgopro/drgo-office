<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 위키 카테고리 트리에 남은 옛 '공지사항' 카테고리 제거.
     * 카테고리 체계 도입 때 wikis.category 문자열에서 자동 생성된 노드로, 공지사항이 독립 게시판으로
     * 이관(소프트 삭제)된 뒤에는 빈 카테고리만 트리에 남아 보인다.
     * 안전장치: 자동 생성 노드와 같은 최상위 '공지사항'만 대상으로 하고, 하위 카테고리·살아 있는 문서·
     * 공지가 아닌 문서(보관함 포함)가 하나라도 연결돼 있으면 건드리지 않는다. 이관된 공지(보관함)만 연결 해제한다.
     */
    public function up(): void
    {
        if (! Schema::hasTable('wiki_categories') || ! Schema::hasColumn('wikis', 'deleted_at')) {
            return;
        }

        $legacy = DB::table('wiki_categories')->whereNull('parent_id')->where('name', '공지사항')->get(['id']);
        foreach ($legacy as $cat) {
            $hasChildren = DB::table('wiki_categories')->where('parent_id', $cat->id)->exists();
            $hasLiveDocs = DB::table('wikis')->where('category_id', $cat->id)->whereNull('deleted_at')->exists();
            $hasNonNoticeDocs = DB::table('wikis')->where('category_id', $cat->id)->where('type', '!=', 'notice')->exists();
            if ($hasChildren || $hasLiveDocs || $hasNonNoticeDocs) {
                continue; // 실제 사용 중(이거나 일반 문서가 보관된) 카테고리 — 보존
            }

            DB::table('wikis')->where('category_id', $cat->id)->update(['category_id' => null]);
            DB::table('wiki_categories')->where('id', $cat->id)->delete();
        }
    }

    public function down(): void
    {
        // 데이터 정리 마이그레이션 — 되돌릴 내용 없음 (카테고리는 위키 '카테고리 편집'에서 다시 만들 수 있음)
    }
};
