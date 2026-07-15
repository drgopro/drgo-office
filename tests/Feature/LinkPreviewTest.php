<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 본문 링크 OG 미리보기 API */
class LinkPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'member']);
    }

    public function test_returns_og_metadata(): void
    {
        Http::fake([
            'example.com/*' => Http::response(<<<'HTML'
                <html><head>
                <title>폴백 제목</title>
                <meta property="og:title" content="닥터고블린 장비 세팅 가이드">
                <meta property="og:description" content="스트리밍 장비 세팅 방법 안내">
                <meta property="og:image" content="https://example.com/thumb.jpg">
                </head><body></body></html>
                HTML, 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);

        $res = $this->actingAs($this->user)->getJson('/api/link-preview?url='.urlencode('https://example.com/guide'));

        $res->assertOk()
            ->assertJsonPath('title', '닥터고블린 장비 세팅 가이드')
            ->assertJsonPath('description', '스트리밍 장비 세팅 방법 안내')
            ->assertJsonPath('image', 'https://example.com/thumb.jpg')
            ->assertJsonPath('host', 'example.com');
    }

    public function test_falls_back_to_title_tag(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><title>일반 페이지 제목</title></head></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->actingAs($this->user)->getJson('/api/link-preview?url='.urlencode('https://example.com/page'))
            ->assertOk()
            ->assertJsonPath('title', '일반 페이지 제목');
    }

    public function test_rejects_invalid_and_internal_urls(): void
    {
        $this->actingAs($this->user)->getJson('/api/link-preview?url=notaurl')->assertUnprocessable();
        $this->actingAs($this->user)->getJson('/api/link-preview?url='.urlencode('http://localhost/admin'))->assertUnprocessable();
        $this->actingAs($this->user)->getJson('/api/link-preview?url='.urlencode('http://127.0.0.1/secret'))->assertUnprocessable();
        $this->actingAs($this->user)->getJson('/api/link-preview?url='.urlencode('http://192.168.0.10/router'))->assertUnprocessable();
        $this->actingAs($this->user)->getJson('/api/link-preview?url='.urlencode('ftp://example.com/file'))->assertUnprocessable();
    }

    public function test_non_html_response_is_rejected(): void
    {
        Http::fake([
            'example.com/*' => Http::response('binary', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->actingAs($this->user)->getJson('/api/link-preview?url='.urlencode('https://example.com/file.pdf'))
            ->assertUnprocessable();
    }
}
