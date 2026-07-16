<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use App\Models\Wiki;
use App\Models\WikiAttachment;
use App\Notifications\ServerErrorAlert;
use App\Services\ServerErrorNotifier;
use Illuminate\Console\Scheduling\Schedule as Scheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** 운영 안전망 — 로그 로테이션, 고아 첨부 정리, 서버 에러 관리자 알림 */
class OpsHardeningTest extends TestCase
{
    use RefreshDatabase;

    // ── 로그 daily 전환 ──

    public function test_default_log_stack_uses_daily_channel(): void
    {
        $this->assertContains('daily', config('logging.channels.stack.channels'));
    }

    // ── 고아 첨부 정리 ──

    public function test_prune_removes_files_left_behind_after_force_delete(): void
    {
        // 운영의 실제 누수 경로: 영구삭제 → FK cascade가 행만 지우고 파일은 잔존
        Storage::fake('local');
        $schedule = Schedule::create(['title' => '검증 일정', 'start_date' => '2026-07-20', 'end_date' => '2026-07-20', 'color' => 'gold']);
        $path = "schedules/{$schedule->id}/left.png";
        Storage::put($path, 'x');
        $schedule->attachments()->create([
            'attachment_type' => 'general', 'file_name' => 'left.png',
            'file_path' => $path, 'mime_type' => 'image/png', 'file_size' => 1,
        ]);

        $schedule->forceDelete();
        $this->assertSame(0, DB::table('schedule_attachments')->count()); // cascade로 행은 이미 삭제
        Storage::assertExists($path); // 파일은 잔존 — 이것이 누수

        touch(Storage::path($path), now()->subDays(3)->getTimestamp());
        $this->artisan('attachments:prune-orphans')->assertSuccessful();

        Storage::assertMissing($path);
    }

    public function test_prune_keeps_attachments_of_soft_deleted_parent(): void
    {
        Storage::fake('local');
        $schedule = Schedule::create(['title' => '복원 대기', 'start_date' => '2026-07-21', 'end_date' => '2026-07-21', 'color' => 'gold']);
        Storage::put('schedules/keep2.png', 'x');
        $attachment = $schedule->attachments()->create([
            'attachment_type' => 'general', 'file_name' => 'keep2.png',
            'file_path' => 'schedules/keep2.png', 'mime_type' => 'image/png', 'file_size' => 1,
        ]);

        $schedule->delete(); // 소프트델리트 — 휴지통 복원 대비 파일 유지

        $this->artisan('attachments:prune-orphans')->assertSuccessful();

        $this->assertDatabaseHas('schedule_attachments', ['id' => $attachment->id]);
        Storage::assertExists('schedules/keep2.png');
    }

    public function test_prune_removes_unreferenced_disk_files_respecting_age_guard(): void
    {
        Storage::fake('local');
        Storage::put('todos/9/orphan-old.png', 'x');
        Storage::put('todos/9/orphan-new.png', 'x');
        touch(Storage::path('todos/9/orphan-old.png'), now()->subDays(3)->getTimestamp());

        $this->artisan('attachments:prune-orphans')->assertSuccessful();

        Storage::assertMissing('todos/9/orphan-old.png'); // 2일 경과 + 미참조 → 삭제
        Storage::assertExists('todos/9/orphan-new.png'); // 최근 파일은 업로드 진행 중일 수 있어 유지
    }

    public function test_prune_removes_abandoned_wiki_pending_uploads(): void
    {
        Storage::fake('local');
        Storage::put('wiki/stale.png', 'x');
        Storage::put('wiki/fresh.png', 'x');
        $stale = WikiAttachment::create(['wiki_id' => null, 'file_name' => 'stale.png', 'file_path' => 'wiki/stale.png', 'mime_type' => 'image/png', 'file_size' => 1]);
        $fresh = WikiAttachment::create(['wiki_id' => null, 'file_name' => 'fresh.png', 'file_path' => 'wiki/fresh.png', 'mime_type' => 'image/png', 'file_size' => 1]);
        $stale->forceFill(['created_at' => now()->subDays(8)])->save();

        $this->artisan('attachments:prune-orphans')->assertSuccessful();

        $this->assertDatabaseMissing('wiki_attachments', ['id' => $stale->id]);
        $this->assertDatabaseHas('wiki_attachments', ['id' => $fresh->id]);
        Storage::assertMissing('wiki/stale.png');
    }

    public function test_prune_links_pending_uploads_still_referenced_in_wiki_content(): void
    {
        // 과거 저장분: 본문에 쓰였지만 wiki_id가 연결 안 된 이미지 — 삭제가 아니라 연결되어야 함
        Storage::fake('local');
        Storage::put('wiki/used.png', 'x');
        $used = WikiAttachment::create(['wiki_id' => null, 'file_name' => 'used.png', 'file_path' => 'wiki/used.png', 'mime_type' => 'image/png', 'file_size' => 1]);
        $used->forceFill(['created_at' => now()->subDays(30)])->save();

        $user = User::factory()->create(['role' => 'admin']);
        $wiki = Wiki::create([
            'title' => '회의록', 'content' => "본문 이미지 ![img](/wiki-files/{$used->id})",
            'created_by' => $user->id, 'updated_by' => $user->id,
        ]);

        $this->artisan('attachments:prune-orphans')->assertSuccessful();

        $this->assertSame($wiki->id, $used->fresh()->wiki_id); // 연결됨
        Storage::assertExists('wiki/used.png'); // 파일 보존
    }

    public function test_wiki_save_links_pending_attachments_in_content(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'admin']);
        $att = WikiAttachment::create(['wiki_id' => null, 'file_name' => 'new.png', 'file_path' => 'wiki/new.png', 'mime_type' => 'image/png', 'file_size' => 1]);

        $res = $this->actingAs($user)->postJson('/wiki', [
            'title' => '새 문서', 'content' => "![img](/wiki-files/{$att->id})",
        ]);
        $res->assertCreated();

        $this->assertSame($res->json('id'), $att->fresh()->wiki_id);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        Storage::fake('local');
        Storage::put('todos/9/orphan-old.png', 'x');
        touch(Storage::path('todos/9/orphan-old.png'), now()->subDays(3)->getTimestamp());

        $this->artisan('attachments:prune-orphans', ['--dry-run' => true])->assertSuccessful();

        Storage::assertExists('todos/9/orphan-old.png');
    }

    public function test_prune_is_scheduled_weekly(): void
    {
        $events = collect(app(Scheduler::class)->events());

        $this->assertTrue(
            $events->contains(fn ($e) => str_contains($e->command ?? '', 'attachments:prune-orphans')),
            'attachments:prune-orphans 스케줄 등록'
        );
    }

    // ── 서버 에러 관리자 알림 ──

    public function test_error_notifier_alerts_admins_once_per_error(): void
    {
        config(['app.error_alerts' => true]);
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);

        $e = new \RuntimeException('DB 연결 실패');
        ServerErrorNotifier::report($e);
        ServerErrorNotifier::report($e); // 동일 에러 재발생 — 스로틀로 미발송

        Notification::assertSentToTimes($admin, ServerErrorAlert::class, 1);
        Notification::assertNotSentTo($member, ServerErrorAlert::class);
    }

    public function test_error_notifier_respects_disabled_flag(): void
    {
        config(['app.error_alerts' => false]);
        Notification::fake();
        User::factory()->create(['role' => 'admin']);

        ServerErrorNotifier::report(new \RuntimeException('무시되어야 함'));

        Notification::assertNothingSent();
    }
}
