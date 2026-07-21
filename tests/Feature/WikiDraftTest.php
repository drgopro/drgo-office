<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** 위키 임시저장(draft) — 자동저장 분리·불러오기 목록·7일 자동 삭제 */
class WikiDraftTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['role' => 'member']);
    }

    public function test_autosave_creates_draft_hidden_from_lists(): void
    {
        $user = $this->member();

        // 자동저장 (is_draft=1)
        $this->actingAs($user)->postJson('/wiki', [
            'title' => '쓰다 만 글', 'content' => '<p>작성 중</p>', 'is_draft' => 1,
        ])->assertCreated();

        $draft = Wiki::first();
        $this->assertTrue($draft->is_draft);

        // 목록·대시보드·전역 검색에 노출되지 않음 (목록은 JSON 임베드라 유니코드 이스케이프 형태로 검증)
        $encoded = trim((string) json_encode('쓰다 만 글'), '"');
        $this->actingAs($user)->get('/wiki')->assertOk()->assertDontSee($encoded, false)->assertDontSee('쓰다 만 글');
        $this->actingAs($user)->get('/')->assertOk()->assertDontSee('쓰다 만 글');
        $this->actingAs($user)->getJson('/api/global-search?q='.urlencode('쓰다 만'))
            ->assertOk()->assertDontSee('쓰다 만 글');
    }

    public function test_publish_clears_draft_flag_and_refreshes_created_at(): void
    {
        $user = $this->member();
        $draft = Wiki::create([
            'title' => '초안', 'content' => '<p>x</p>', 'is_draft' => true, 'created_by' => $user->id,
        ]);
        $draft->forceFill(['created_at' => now()->subDays(5)])->save();

        // 등록 버튼 = is_draft 0으로 발행
        $this->actingAs($user)->patchJson("/wiki/{$draft->id}", [
            'title' => '발행된 글', 'content' => '<p>완성</p>', 'is_draft' => 0,
        ])->assertOk();

        $draft->refresh();
        $this->assertFalse($draft->is_draft);
        $this->assertTrue($draft->created_at->gt(now()->subDay()), '발행 시점으로 작성일 갱신');
        $this->actingAs($user)->get('/wiki')->assertOk()
            ->assertSee(trim((string) json_encode('발행된 글'), '"'), false);
    }

    public function test_drafts_api_returns_only_own_drafts(): void
    {
        $me = $this->member();
        $other = $this->member();
        Wiki::create(['title' => '내 초안', 'content' => 'x', 'is_draft' => true, 'created_by' => $me->id]);
        $othersDraft = Wiki::create(['title' => '남의 초안', 'content' => 'x', 'is_draft' => true, 'created_by' => $other->id]);
        Wiki::create(['title' => '발행 글', 'content' => 'x', 'is_draft' => false, 'created_by' => $me->id]);

        $titles = collect($this->actingAs($me)->getJson('/api/wiki/drafts')->assertOk()->json())->pluck('title');
        $this->assertSame(['내 초안'], $titles->all());

        // 남의 초안 단건 접근 차단
        $this->actingAs($me)->getJson("/api/wiki/drafts/{$othersDraft->id}")->assertNotFound();
    }

    public function test_draft_show_returns_content_for_resume(): void
    {
        $me = $this->member();
        $draft = Wiki::create(['title' => '이어쓰기', 'content' => '<p>본문</p>', 'is_draft' => true, 'created_by' => $me->id]);

        $this->actingAs($me)->getJson("/api/wiki/drafts/{$draft->id}")
            ->assertOk()
            ->assertJson(['id' => $draft->id, 'title' => '이어쓰기', 'content' => '<p>본문</p>']);
    }

    public function test_prune_deletes_week_old_drafts_only(): void
    {
        Storage::fake('local');
        $user = $this->member();
        $old = Wiki::create(['title' => '오래된 초안', 'content' => 'x', 'is_draft' => true, 'created_by' => $user->id]);
        $old->forceFill(['updated_at' => now()->subDays(8)])->save();
        Storage::put('wiki/draft-img.png', 'x');
        $old->attachments()->create(['file_name' => 'draft-img.png', 'file_path' => 'wiki/draft-img.png', 'mime_type' => 'image/png', 'file_size' => 1]);

        $fresh = Wiki::create(['title' => '최근 초안', 'content' => 'x', 'is_draft' => true, 'created_by' => $user->id]);
        $published = Wiki::create(['title' => '오래된 발행 글', 'content' => 'x', 'is_draft' => false, 'created_by' => $user->id]);
        $published->forceFill(['updated_at' => now()->subDays(30)])->save();

        $this->artisan('wiki:prune-drafts')->assertSuccessful();

        $this->assertDatabaseMissing('wikis', ['id' => $old->id]);
        Storage::assertMissing('wiki/draft-img.png'); // 첨부 파일도 함께 삭제
        $this->assertDatabaseHas('wikis', ['id' => $fresh->id]);
        $this->assertDatabaseHas('wikis', ['id' => $published->id]); // 발행 글은 건드리지 않음
    }

    public function test_prune_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events());
        $this->assertTrue($events->contains(fn ($e) => str_contains($e->command ?? '', 'wiki:prune-drafts')));
    }
}
