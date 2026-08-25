<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 견적서 빌더 1분 자동 임시저장 — 정식 저장과 별개 스냅샷.
 * 저장/불러오기, 정식 저장 시 비워짐, 잘못된 페이로드 차단.
 */
class EstimateDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Estimate $estimate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $this->estimate = Estimate::create([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'service_total' => 0, 'total_amount' => 0,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ]);
    }

    public function test_draft_saves_and_loads_without_touching_real_estimate(): void
    {
        $draft = [
            'title' => '작성 중 견적', 'client_name' => '홍길동',
            'product_items' => [['name' => '카메라', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000]],
        ];

        $this->actingAs($this->admin)->postJson("/api/estimates/{$this->estimate->id}/draft", ['draft' => $draft])
            ->assertOk()->assertJsonStructure(['saved_at']);

        // 임시저장은 정식 필드를 건드리지 않는다
        $fresh = $this->estimate->fresh();
        $this->assertNull($fresh->title);
        $this->assertSame([], $fresh->product_items);
        $this->assertNotNull($fresh->draft_saved_at);

        $loaded = $this->actingAs($this->admin)->getJson("/api/estimates/{$this->estimate->id}/draft")
            ->assertOk()->json();
        $this->assertSame('작성 중 견적', $loaded['draft']['title']);
        $this->assertSame('카메라', $loaded['draft']['product_items'][0]['name']);
        $this->assertNotNull($loaded['saved_at']);
    }

    public function test_real_save_clears_draft(): void
    {
        $this->actingAs($this->admin)->postJson("/api/estimates/{$this->estimate->id}/draft", [
            'draft' => ['title' => '임시'],
        ])->assertOk();

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$this->estimate->id}", [
            'title' => '정식 저장',
        ])->assertOk();

        $fresh = $this->estimate->fresh();
        $this->assertNull($fresh->draft);
        $this->assertNull($fresh->draft_saved_at);
        $this->assertSame('정식 저장', $fresh->title);

        $this->actingAs($this->admin)->getJson("/api/estimates/{$this->estimate->id}/draft")
            ->assertOk()->assertJson(['draft' => null, 'saved_at' => null]);
    }

    public function test_rejects_invalid_draft_payload(): void
    {
        // 배열이 아닌 draft
        $this->actingAs($this->admin)->postJson("/api/estimates/{$this->estimate->id}/draft", [
            'draft' => '문자열',
        ])->assertStatus(422)->assertJsonValidationErrors(['draft']);

        // draft 누락
        $this->actingAs($this->admin)->postJson("/api/estimates/{$this->estimate->id}/draft", [])
            ->assertStatus(422);

        // 품목 수 상한 초과
        $items = array_fill(0, 301, ['name' => 'X']);
        $this->actingAs($this->admin)->postJson("/api/estimates/{$this->estimate->id}/draft", [
            'draft' => ['product_items' => $items],
        ])->assertStatus(422)->assertJsonValidationErrors(['draft.product_items']);
    }
}
