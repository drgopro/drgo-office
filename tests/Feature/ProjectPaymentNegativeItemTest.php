<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 결제 정보 — 견적서의 음수(할인) 항목이 결제 항목으로 넘어와도 저장 가능 */
class ProjectPaymentNegativeItemTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeProject(): Project
    {
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);

        return Project::create([
            'client_id' => $client->id,
            'name' => '테스트 프로젝트',
            'project_type' => 'visit',
            'stage' => 'consulting',
        ]);
    }

    public function test_payment_saves_with_negative_discount_item(): void
    {
        // 견적서에 재방문 할인 -50,000원이 있으면 결제 항목에도 음수로 자동 채워짐 — 422 없이 저장
        $project = $this->makeProject();

        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/payment", [
            'amount' => 650000,
            'paid_at' => '2026-09-08',
            'method' => '카드',
            'items' => [
                ['name' => '세팅비 할인', 'qty' => 1, 'price' => -50000, 'source' => 'estimate'],
                ['name' => '개인형 방문 세팅', 'qty' => 1, 'price' => 300000, 'source' => 'estimate'],
                ['name' => '카메라 시연', 'qty' => 1, 'price' => 100000, 'source' => 'estimate'],
            ],
        ])->assertOk();

        $payment = ProjectPayment::where('project_id', $project->id)->where('type', 'charge')->first();
        $this->assertNotNull($payment);
        $this->assertSame(650000, (int) $payment->amount);
        $this->assertSame(-50000, (int) $payment->items[0]['price']);
    }

    public function test_payment_update_recomputes_amount_including_negative_item(): void
    {
        // 결제 수정 시 항목 합산이 음수 항목을 차감해 재계산
        $project = $this->makeProject();
        $payment = ProjectPayment::create([
            'project_id' => $project->id, 'type' => 'charge', 'amount' => 100000,
            'items' => [['name' => '세팅비', 'qty' => 1, 'price' => 100000]],
            'paid_at' => '2026-09-08',
        ]);

        $this->actingAs($this->admin())->patchJson("/api/projects/{$project->id}/payments/{$payment->id}", [
            'items' => [
                ['name' => '세팅비', 'qty' => 1, 'price' => 100000],
                ['name' => '재방문 할인', 'qty' => 1, 'price' => -30000],
            ],
        ])->assertOk();

        $this->assertSame(70000, (int) $payment->fresh()->amount); // 100,000 − 30,000
    }
}
