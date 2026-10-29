<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 배포 후 새로고침 안내 — 버전 API와 셸의 안내 띠/버전 심기 */
class AppUpdateBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_api_returns_current_version_without_caching(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $res = $this->actingAs($user)->getJson('/api/app-version')->assertOk();

        $res->assertJson(['version' => app(AppVersion::class)->current()]);
        $this->assertNotSame('', $res->json('version'));
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
    }

    public function test_version_api_requires_login(): void
    {
        $this->getJson('/api/app-version')->assertUnauthorized();
    }

    public function test_shell_embeds_version_and_update_banner(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $html = $this->actingAs($user)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="appUpdateBar"', $html);
        $this->assertStringContainsString('새로운 업데이트가 있습니다.', $html);
        $this->assertStringContainsString('window.DRGO_APP_VERSION = "'.app(AppVersion::class)->current().'"', $html);
        $this->assertStringContainsString("fetch('/api/app-version'", $html);
    }

    public function test_deploy_stamp_command_writes_stamp_that_changes_the_version(): void
    {
        $service = app(AppVersion::class);
        $path = $service->stampPath();
        $backup = is_file($path) ? file_get_contents($path) : null;
        $before = $service->current();

        try {
            @unlink($path);
            $this->artisan('deploy:stamp')->assertSuccessful();

            $this->assertFileExists($path);
            $this->assertMatchesRegularExpression('/^([0-9a-f]{12}|unknown)@\d{14}$/', trim((string) file_get_contents($path)));
            $this->assertStringStartsWith('stamp:', $service->raw());
            $this->assertNotSame($before, $service->current());
        } finally {
            $backup === null ? @unlink($path) : file_put_contents($path, $backup);
        }
    }

    public function test_banner_is_not_rendered_for_guests_on_login_page(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="appUpdateBar"', $html);
    }
}
