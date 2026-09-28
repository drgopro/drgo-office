<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 마케팅 활용 동의/거부 변경 다이제스트 — 변경 시각 기록 + 전날 변경분 아웃바운드 발송 */
class MarketingConsentDigestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.channeltalk.access_key' => 'k',
            'services.channeltalk.access_secret' => 's',
            'services.channeltalk.group' => '아웃바운드',
            'services.channeltalk.bot_name' => '오피스봇',
        ]);
    }

    public function test_update_json_records_consent_changed_at_only_when_value_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '세팅', 'stage' => 'consulting']);

        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'marketing_consent' => true,
        ])->assertSuccessful();
        $changedAt = $project->fresh()->marketing_consent_updated_at;
        $this->assertNotNull($changedAt);

        // 같은 값으로 다시 저장 — 시각 유지
        $this->travel(1)->hours();
        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'marketing_consent' => true,
        ])->assertSuccessful();
        $this->assertTrue($changedAt->equalTo($project->fresh()->marketing_consent_updated_at));

        // 다른 값으로 변경 — 시각 갱신
        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}", [
            'marketing_consent' => false,
        ])->assertSuccessful();
        $this->assertTrue($project->fresh()->marketing_consent_updated_at->greaterThan($changedAt));
    }

    public function test_digest_sends_yesterday_consented_and_refused_projects_to_outbound(): void
    {
        Http::fake(['api.channel.io/*' => Http::response(['ok' => true])]);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $yesterday = now()->subDay()->setTime(14, 0);

        Project::create(['client_id' => $client->id, 'name' => '동의 프로젝트', 'stage' => 'consulting', 'marketing_consent' => true, 'marketing_consent_updated_at' => $yesterday]);
        Project::create(['client_id' => $client->id, 'name' => '거부 프로젝트', 'stage' => 'consulting', 'marketing_consent' => false, 'marketing_consent_updated_at' => $yesterday]);
        // 제외 대상: 이틀 전 변경, 미확인으로 되돌림
        Project::create(['client_id' => $client->id, 'name' => '이틀전 프로젝트', 'stage' => 'consulting', 'marketing_consent' => true, 'marketing_consent_updated_at' => now()->subDays(2)]);
        Project::create(['client_id' => $client->id, 'name' => '되돌린 프로젝트', 'stage' => 'consulting', 'marketing_consent' => null, 'marketing_consent_updated_at' => $yesterday]);

        $this->artisan('marketing:consent-digest')->assertSuccessful();

        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), '/groups/@'.rawurlencode('아웃바운드').'/messages')) {
                return false;
            }
            $text = $r['blocks'][0]['value'] ?? '';

            return str_contains($text, '동의/거부 변경 2건')
                && str_contains($text, '▶ 동의 1건')
                && str_contains($text, '동의 프로젝트 — 고블린')
                && str_contains($text, '▶ 거부 1건')
                && str_contains($text, '거부 프로젝트 — 고블린')
                && ! str_contains($text, '이틀전 프로젝트')
                && ! str_contains($text, '되돌린 프로젝트');
        });
    }

    public function test_digest_skips_when_no_changes(): void
    {
        Http::fake();
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        Project::create(['client_id' => $client->id, 'name' => '오늘 프로젝트', 'stage' => 'consulting', 'marketing_consent' => true, 'marketing_consent_updated_at' => now()]);

        $this->artisan('marketing:consent-digest')->assertSuccessful();

        Http::assertNothingSent();
    }
}
