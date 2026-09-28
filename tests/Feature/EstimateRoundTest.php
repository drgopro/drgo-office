<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 견적서 추가 차수(N차 추가 견적) — 생성 규칙, 표시 번호, 목록 아코디언, 최종 견적서 1장 합산 */
class EstimateRoundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function makePaidParent(): Estimate
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal', 'phone' => '010-1234-5678']);

        return Estimate::create([
            'estimate_no' => 200 + Estimate::count(),
            'status' => 'paid',
            'title' => '스튜디오 세팅 견적',
            'client_id' => $client->id,
            'client_nickname' => '고블린',
            'client_phone' => '010-1234-5678',
            'product_items' => [[
                'name' => '카메라 X100', 'sale_price' => 500000, 'qty' => 1, 'subtotal' => 500000, 'category' => '카메라',
            ]],
            'service_items' => [],
            'product_total' => 500000,
            'service_total' => 0,
            'total_amount' => 500000,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_round_can_be_created_from_paid_estimate(): void
    {
        $parent = $this->makePaidParent();

        $res = $this->actingAs($this->admin)->postJson("/api/estimates/{$parent->id}/rounds");
        $res->assertCreated();

        $round = Estimate::find($res->json('id'));
        $this->assertSame($parent->id, $round->parent_estimate_id);
        $this->assertSame(2, $round->round);
        $this->assertSame('created', $round->status);
        $this->assertSame('고블린', $round->client_nickname); // 주문 정보 복사
        $this->assertSame('200-2', (string) $round->display_no); // 부모 번호-차수

        // 다음 차수는 3차
        $res2 = $this->actingAs($this->admin)->postJson("/api/estimates/{$parent->id}/rounds");
        $this->assertSame(3, Estimate::find($res2->json('id'))->round);
    }

    public function test_round_creation_rules(): void
    {
        // 발행/결제 전 견적서에는 차수를 만들 수 없음
        $draft = $this->makePaidParent();
        $draft->update(['status' => 'created']);
        $this->actingAs($this->admin)->postJson("/api/estimates/{$draft->id}/rounds")->assertStatus(422);

        // 차수에는 추가 차수를 만들 수 없음
        $parent = $this->makePaidParent();
        $round = Estimate::find($this->actingAs($this->admin)->postJson("/api/estimates/{$parent->id}/rounds")->json('id'));
        $this->actingAs($this->admin)->postJson("/api/estimates/{$round->id}/rounds")->assertStatus(422);
    }

    public function test_round_does_not_get_own_estimate_no_when_issued(): void
    {
        $parent = $this->makePaidParent();
        $round = Estimate::find($this->actingAs($this->admin)->postJson("/api/estimates/{$parent->id}/rounds")->json('id'));

        $this->actingAs($this->admin)->patchJson("/api/estimates/{$round->id}", [
            'product_items' => [['name' => '추가 마이크', 'sale_price' => 100000, 'qty' => 1, 'subtotal' => 100000]],
            'service_items' => [],
            'status' => 'issued',
        ])->assertOk();

        $round->refresh();
        $this->assertNull($round->estimate_no);
        $this->assertSame('200-2', (string) $round->display_no);
    }

    public function test_list_shows_parents_only_with_rounds_nested(): void
    {
        $parent = $this->makePaidParent();
        $round = Estimate::find($this->actingAs($this->admin)->postJson("/api/estimates/{$parent->id}/rounds")->json('id'));

        $rows = collect($this->actingAs($this->admin)->getJson('/api/estimates')->assertOk()->json());
        $this->assertTrue($rows->pluck('id')->contains($parent->id));
        $this->assertFalse($rows->pluck('id')->contains($round->id)); // 차수는 최상위에 없음
        $this->assertSame($round->id, $rows->firstWhere('id', $parent->id)['rounds'][0]['id']); // 부모 안에 중첩
    }

    public function test_parent_with_rounds_cannot_be_deleted_and_paid_round_protected(): void
    {
        $parent = $this->makePaidParent();
        $round = Estimate::find($this->actingAs($this->admin)->postJson("/api/estimates/{$parent->id}/rounds")->json('id'));

        $this->actingAs($this->admin)->deleteJson("/api/estimates/{$parent->id}")->assertStatus(422);

        $round->update(['status' => 'paid']);
        $this->actingAs($this->admin)->deleteJson("/api/estimates/{$round->id}")->assertStatus(422);

        $round->update(['status' => 'issued']);
        $this->actingAs($this->admin)->deleteJson("/api/estimates/{$round->id}")->assertOk();
        $this->actingAs($this->admin)->deleteJson("/api/estimates/{$parent->id}")->assertOk();
    }

    public function test_public_view_merges_rounds_into_final_document(): void
    {
        $parent = $this->makePaidParent();
        $round = Estimate::find($this->actingAs($this->admin)->postJson("/api/estimates/{$parent->id}/rounds")->json('id'));
        $round->update([
            'status' => 'issued',
            'product_items' => [['name' => '추가 조명 세트', 'sale_price' => 150000, 'qty' => 1, 'subtotal' => 150000]],
            'total_amount' => 150000,
        ]);

        // 차수의 공개 링크는 부모 문서로 위임 — 항상 최종 견적서 1장
        $this->assertSame($parent->publicUrl(), $round->fresh()->publicUrl());

        $token = $parent->fresh()->share_token;
        $res = $this->actingAs($this->admin)->get("/estimate-view/{$token}");
        $res->assertOk()
            ->assertSee('2차 추가 견적')
            ->assertSee('추가 조명 세트')
            ->assertSee('최종 정산')
            ->assertSee(number_format(650000)); // 500,000 + 150,000

        // 작성 중(created) 차수는 의뢰자 문서에 보이지 않음
        $round->update(['status' => 'created']);
        $this->actingAs($this->admin)->get("/estimate-view/{$token}")->assertOk()
            ->assertDontSee('2차 추가 견적');
    }

    public function test_builder_shows_round_section_and_round_banner(): void
    {
        $parent = $this->makePaidParent();
        $round = Estimate::find($this->actingAs($this->admin)->postJson("/api/estimates/{$parent->id}/rounds")->json('id'));

        // 부모 빌더 — 추가 차수 영역
        $this->actingAs($this->admin)->get("/estimates/{$parent->id}/edit")->assertOk()
            ->assertSee('추가 차수')
            ->assertSee('+ 추가 견적 만들기')
            ->assertSee('id="roundsSection"', false);

        // 차수 빌더 — 부모 안내 배너
        $this->actingAs($this->admin)->get("/estimates/{$round->id}/edit")->assertOk()
            ->assertSee('2차 추가 견적입니다');
    }
}
