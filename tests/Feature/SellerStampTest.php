<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** 직인 이미지 — 관리 페이지 업로드 + 견적서 판매처 영역 배경 표시 */
class SellerStampTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->admin = User::factory()->create(['role' => 'master']);
    }

    public function test_upload_serve_and_delete_stamp(): void
    {
        // 업로드 — 설정 저장 + 파일 보관
        $this->actingAs($this->admin)->post('/api/admin/seller-stamp', [
            'stamp' => UploadedFile::fake()->image('stamp.png', 300, 300),
        ])->assertOk();
        $path = Setting::get('seller_stamp_path');
        $this->assertNotNull($path);
        Storage::assertExists($path);

        // 공개 서빙 — 로그인 없이 접근 가능 (의뢰자 공개 견적서용)
        $this->get('/seller-stamp')->assertOk();

        // 재업로드 시 이전 파일 정리
        $this->actingAs($this->admin)->post('/api/admin/seller-stamp', [
            'stamp' => UploadedFile::fake()->image('stamp2.png', 200, 200),
        ])->assertOk();
        Storage::assertMissing($path);
        Storage::assertExists(Setting::get('seller_stamp_path'));

        // 삭제 — 설정·파일 모두 제거, 서빙은 404
        $last = Setting::get('seller_stamp_path');
        $this->actingAs($this->admin)->delete('/api/admin/seller-stamp')->assertOk();
        Storage::assertMissing($last);
        $this->assertNull(Setting::get('seller_stamp_path'));
        $this->get('/seller-stamp')->assertNotFound();
    }

    public function test_upload_rejects_non_image_and_non_admin(): void
    {
        $this->actingAs($this->admin)->post('/api/admin/seller-stamp', [
            'stamp' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->post('/api/admin/seller-stamp', [
            'stamp' => UploadedFile::fake()->image('stamp.png'),
        ], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_print_shows_stamp_only_when_registered(): void
    {
        $estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);

        // 미등록 시 직인 이미지 태그 없음 (CSS 정의는 항상 있으므로 img 태그 기준)
        $html = $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/print")->assertOk()->getContent();
        $this->assertStringNotContainsString('class="seller-stamp"', $html);

        // 등록 후 내부 인쇄·의뢰자 공개 링크 모두 표시
        $this->actingAs($this->admin)->post('/api/admin/seller-stamp', [
            'stamp' => UploadedFile::fake()->image('stamp.png', 300, 300),
        ])->assertOk();

        $html = $this->actingAs($this->admin)->get("/estimates/{$estimate->id}/print")->assertOk()->getContent();
        $this->assertStringContainsString('class="seller-stamp"', $html);

        $public = $this->get($estimate->fresh()->publicUrl())->assertOk()->getContent();
        $this->assertStringContainsString('class="seller-stamp"', $public);
    }
}
