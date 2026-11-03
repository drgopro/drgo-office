<?php

namespace Tests\Feature;

use App\Models\AsRequest;
use App\Models\Client;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** AS 현황판 — 의뢰자·프로젝트 연동 필수, 4단계 진행, 무상/유상 기재 */
class AsBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->client = Client::create(['nickname' => '옴니버', 'grade' => 'normal']);
        $this->project = Project::create([
            'name' => '옴니버 세팅', 'project_type' => 'visit', 'stage' => 'consulting',
            'client_id' => $this->client->id,
        ]);
    }

    public function test_권한_없는_사용자는_접근_불가(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $this->actingAs($guest)->get('/as')->assertForbidden();
        $this->actingAs($guest)->getJson('/api/as-requests')->assertForbidden();
    }

    public function test_등록은_의뢰자와_프로젝트_연동_필수(): void
    {
        $this->actingAs($this->admin)->postJson('/api/as-requests', [
            'product_name' => '마이크',
        ])->assertUnprocessable()->assertJsonValidationErrors(['client_id', 'project_id']);

        // 다른 의뢰자의 프로젝트는 거부
        $other = Client::create(['nickname' => '남의집', 'grade' => 'normal']);
        $otherProject = Project::create([
            'name' => '남의 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting',
            'client_id' => $other->id,
        ]);
        $this->actingAs($this->admin)->postJson('/api/as-requests', [
            'client_id' => $this->client->id,
            'project_id' => $otherProject->id,
            'product_name' => '마이크',
        ])->assertUnprocessable()->assertJsonValidationErrors(['project_id']);
    }

    public function test_등록_시_사무실_접수_상태와_접수_시각_기록(): void
    {
        $this->actingAs($this->admin)->postJson('/api/as-requests', [
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'product_name' => '오디오 인터페이스',
            'description' => 'USB 케이블·어댑터 동봉, 전원 불량',
        ])->assertOk()->assertJsonPath('item.status', 'received');

        $item = AsRequest::firstOrFail();
        $this->assertNotNull($item->received_at);
        $this->assertSame('오디오 인터페이스', $item->product_name);
    }

    public function test_단계_변경은_전환_시각을_기록하고_되돌리면_초기화(): void
    {
        $item = AsRequest::create([
            'client_id' => $this->client->id, 'project_id' => $this->project->id,
            'product_name' => '마이크', 'received_at' => now()->subDays(3),
        ]);

        // 사무실 접수 → AS 완료로 건너뛰어도 중간 단계(발송) 시각까지 채워진다
        $this->actingAs($this->admin)->postJson("/api/as-requests/{$item->id}/status", ['status' => 'repaired'])->assertOk();
        $item->refresh();
        $this->assertSame('repaired', $item->status);
        $this->assertNotNull($item->sent_at);
        $this->assertNotNull($item->repaired_at);
        $this->assertNull($item->done_at);

        // 완료 → 접수로 되돌리면 이후 단계 시각은 지워진다
        $this->actingAs($this->admin)->postJson("/api/as-requests/{$item->id}/status", ['status' => 'received'])->assertOk();
        $item->refresh();
        $this->assertSame('received', $item->status);
        $this->assertNull($item->sent_at);
        $this->assertNull($item->repaired_at);
        $this->assertNotNull($item->received_at);
    }

    public function test_유상_비용_기재와_무상_전환_시_비용_초기화(): void
    {
        $item = AsRequest::create([
            'client_id' => $this->client->id, 'project_id' => $this->project->id,
            'product_name' => '믹서', 'received_at' => now(),
        ]);

        $this->actingAs($this->admin)->patchJson("/api/as-requests/{$item->id}", [
            'client_id' => $this->client->id, 'project_id' => $this->project->id,
            'product_name' => '믹서', 'cost_type' => 'paid', 'cost_amount' => 88000,
        ])->assertOk()->assertJsonPath('item.cost_amount', 88000);

        // 무상으로 바꾸면 비용은 비워진다
        $this->actingAs($this->admin)->patchJson("/api/as-requests/{$item->id}", [
            'client_id' => $this->client->id, 'project_id' => $this->project->id,
            'product_name' => '믹서', 'cost_type' => 'free', 'cost_amount' => 88000,
        ])->assertOk()->assertJsonPath('item.cost_amount', null);
    }

    public function test_목록_상태_필터와_카운트(): void
    {
        AsRequest::create(['client_id' => $this->client->id, 'project_id' => $this->project->id,
            'product_name' => '마이크', 'status' => 'received', 'received_at' => now()]);
        AsRequest::create(['client_id' => $this->client->id, 'project_id' => $this->project->id,
            'product_name' => '캠', 'status' => 'done', 'received_at' => now(), 'done_at' => now()]);

        $res = $this->actingAs($this->admin)->getJson('/api/as-requests?status=received')->assertOk();
        $this->assertCount(1, $res->json('items'));
        $this->assertSame('마이크', $res->json('items.0.product_name'));
        $this->assertSame(1, $res->json('counts.received'));
        $this->assertSame(1, $res->json('counts.done'));
    }

    public function test_의뢰자_프로젝트_목록_api(): void
    {
        $this->actingAs($this->admin)->getJson("/api/as-requests/client-projects/{$this->client->id}")
            ->assertOk()->assertJsonPath('0.name', '옴니버 세팅');
    }

    public function test_제품명_검색_api(): void
    {
        Product::create(['sku' => 'AS-TEST-1', 'name' => '오디오 인터페이스 X2', 'option_name' => '블랙', 'sale_price' => 100000, 'is_active' => true]);
        Product::create(['sku' => 'AS-TEST-2', 'name' => '비활성 제품', 'sale_price' => 1000, 'is_active' => false]);

        $res = $this->actingAs($this->admin)->getJson('/api/as-requests/product-search?q=오디오')->assertOk();
        $this->assertCount(1, $res->json());
        $this->assertSame('오디오 인터페이스 X2 · 블랙', $res->json('0.label'));

        // 비활성 제품은 검색되지 않는다
        $this->assertCount(0, $this->actingAs($this->admin)->getJson('/api/as-requests/product-search?q=비활성')->json());
    }

    public function test_현황판_페이지_렌더(): void
    {
        $this->actingAs($this->admin)->get('/as')->assertOk()
            ->assertSee('AS 현황')
            ->assertSee('api/as-requests', false)
            ->assertSee('asOpenModal', false);
    }
}
