<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** 지도 동선 조회 — 주소를 좌표로 변환해 카카오/네이버 공식 길찾기 링크 생성 */
class GeocodeRouteTest extends TestCase
{
    use RefreshDatabase;

    private const HQ = '서울특별시 동작구 장승배기로 142';

    private const DEST = '서울 구로구 고척로27길 81-22';

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/geocode-route?from=a&to=b')->assertUnauthorized();
    }

    public function test_returns_422_when_key_not_configured(): void
    {
        config(['services.kakao.rest_key' => null]);
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)
            ->getJson('/api/geocode-route?from='.urlencode(self::HQ).'&to='.urlencode(self::DEST))
            ->assertStatus(422);
    }

    public function test_builds_coordinate_route_urls(): void
    {
        config(['services.kakao.rest_key' => 'test-key']);
        Http::fake([
            'dapi.kakao.com/v2/local/search/address.json*' => Http::sequence()
                ->push(['documents' => [['x' => '126.939', 'y' => '37.5048']]])   // 출발지
                ->push(['documents' => [['x' => '126.8523', 'y' => '37.5011']]]), // 도착지
        ]);
        $user = User::factory()->create(['role' => 'member']);

        $res = $this->actingAs($user)
            ->getJson('/api/geocode-route?from='.urlencode(self::HQ).'&to='.urlencode(self::DEST))
            ->assertOk();

        $kakao = $res->json('kakao_url');
        $naver = $res->json('naver_url');
        $this->assertStringStartsWith('https://map.kakao.com/link/from/', $kakao);
        $this->assertStringContainsString(',37.5048,126.939/to/', $kakao);
        $this->assertStringContainsString(',37.5011,126.8523', $kakao);
        // 네이버는 lng,lat,이름 순 + 자동차 길찾기
        $this->assertStringStartsWith('https://map.naver.com/p/directions/126.939,37.5048,', $naver);
        $this->assertStringContainsString('/126.8523,37.5011,', $naver);
        $this->assertStringContainsString('/-/car', $naver);

        // API 키 헤더 확인
        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'KakaoAK test-key'));
    }

    public function test_falls_back_to_keyword_search_and_fails_cleanly(): void
    {
        config(['services.kakao.rest_key' => 'test-key']);
        Http::fake([
            'dapi.kakao.com/v2/local/search/address.json*' => Http::response(['documents' => []]),
            'dapi.kakao.com/v2/local/search/keyword.json*' => Http::response(['documents' => []]),
        ]);
        $user = User::factory()->create(['role' => 'member']);

        // 주소·키워드 검색 모두 실패 → 422 (프론트는 이름 기반 URL로 폴백)
        $this->actingAs($user)
            ->getJson('/api/geocode-route?from=없는주소&to=없는주소2')
            ->assertStatus(422);
        Http::assertSentCount(4); // 각 주소마다 address → keyword 순 시도
    }
}
