<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectBilling;
use App\Models\ProjectPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 견적서에 프로젝트를 뒤늦게 연결 — 이미 받은 포트폴리오 동의(#294)와 결제 내역(#293)이 그 프로젝트로 넘어간다.
 * 의뢰자 없이 만든 견적서는 프로젝트 없이 동의·결제까지 진행될 수 있어, 나중 연결 시점에 반영해야 한다.
 */
class EstimateLateProjectLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'master']);
        $this->client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
    }

    private function makeEstimate(array $over = []): Estimate
    {
        return Estimate::create(array_merge([
            'status' => 'completed', 'service_items' => [],
            'product_items' => [['name' => '원격', 'qty' => 1, 'sale_price' => 50000, 'subtotal' => 50000]],
            'product_total' => 50000, 'service_total' => 0, 'total_amount' => 50000,
            'validity_days' => 3, 'created_by' => $this->admin->id,
        ], $over));
    }

    private function makeProject(string $name = '세팅'): Project
    {
        return Project::create(['client_id' => $this->client->id, 'name' => $name, 'stage' => 'consulting']);
    }

    private function link(Estimate $estimate, Project $project): void
    {
        $this->actingAs($this->admin)
            ->patchJson("/api/estimates/{$estimate->id}", ['client_id' => $this->client->id, 'project_id' => $project->id])
            ->assertOk();
    }

    public function test_marketing_choice_recorded_before_linking_is_applied_to_the_project(): void
    {
        $agreedEstimate = $this->makeEstimate();
        $agreedEstimate->forceFill(['agreements' => ['marketing' => ['at' => '2026-11-21 17:15:57', 'agreed' => true]]])->save();
        $agreedProject = $this->makeProject('동의');
        $this->link($agreedEstimate, $agreedProject);
        $this->assertTrue($agreedProject->fresh()->marketing_consent);
        $this->assertNotNull($agreedProject->fresh()->marketing_consent_updated_at);

        $declinedEstimate = $this->makeEstimate();
        $declinedEstimate->forceFill(['agreements' => ['marketing' => ['at' => '2026-11-21 17:15:57', 'agreed' => false]]])->save();
        $declinedProject = $this->makeProject('거부');
        $this->link($declinedEstimate, $declinedProject);
        $this->assertFalse($declinedProject->fresh()->marketing_consent);
    }

    public function test_linking_without_a_marketing_record_leaves_the_project_value_alone(): void
    {
        $project = $this->makeProject();
        $project->forceFill(['marketing_consent' => true])->save();

        $this->link($this->makeEstimate(), $project);

        $this->assertTrue($project->fresh()->marketing_consent);
    }

    public function test_linking_a_project_to_a_paid_estimate_records_the_payment_once(): void
    {
        $estimate = $this->makeEstimate(['status' => 'paid', 'paid_at' => '2026-11-21 17:16:00']);
        $project = $this->makeProject();

        $this->link($estimate, $project);
        $this->link($estimate, $project); // 같은 프로젝트로 다시 저장해도 중복 기록 없음

        $charges = ProjectPayment::where('estimate_id', $estimate->id)->where('type', 'charge')->get();
        $this->assertCount(1, $charges);
        $this->assertSame($project->id, $charges[0]->project_id);
        $this->assertSame(50000, $charges[0]->amount);
        $this->assertSame('2026-11-21', $charges[0]->paid_at->format('Y-m-d')); // 연결한 날이 아니라 실제 결제일
    }

    public function test_changing_the_project_of_a_paid_estimate_moves_its_payment(): void
    {
        $first = $this->makeProject('첫 프로젝트');
        $second = $this->makeProject('옮길 프로젝트');
        $estimate = $this->makeEstimate(['status' => 'paid', 'client_id' => $this->client->id, 'project_id' => $first->id]);
        // 이 견적서의 청구(함께 옮겨짐)와 이전 프로젝트 고유의 청구(남고, 연결만 해제)
        $ownBilling = ProjectBilling::create(['project_id' => $first->id, 'estimate_id' => $estimate->id, 'amount' => 50000, 'billed_at' => '2026-11-21', 'status' => 'paid']);
        $foreignBilling = ProjectBilling::create(['project_id' => $first->id, 'amount' => 30000, 'billed_at' => '2026-11-21', 'status' => 'paid']);
        $charge = ProjectPayment::create([
            'project_id' => $first->id, 'type' => 'charge', 'estimate_id' => $estimate->id, 'billing_id' => $ownBilling->id,
            'amount' => 50000, 'paid_at' => '2026-11-21', 'recorded_by' => $this->admin->id,
        ]);
        $refund = ProjectPayment::create([
            'project_id' => $first->id, 'type' => 'refund', 'estimate_id' => $estimate->id, 'billing_id' => $foreignBilling->id,
            'parent_payment_id' => $charge->id, 'amount' => -10000, 'paid_at' => '2026-11-22', 'recorded_by' => $this->admin->id,
        ]);

        $this->link($estimate, $second);

        $this->assertSame(0, ProjectPayment::where('project_id', $first->id)->count());
        $this->assertSame(2, ProjectPayment::where('project_id', $second->id)->where('estimate_id', $estimate->id)->count());
        $this->assertSame($second->id, $ownBilling->fresh()->project_id);
        $this->assertSame($ownBilling->id, $charge->fresh()->billing_id);
        $this->assertSame($first->id, $foreignBilling->fresh()->project_id);
        $this->assertNull($refund->fresh()->billing_id);
    }

    public function test_linking_an_unpaid_estimate_records_no_payment(): void
    {
        $estimate = $this->makeEstimate();
        $this->link($estimate, $this->makeProject());

        $this->assertSame(0, ProjectPayment::count());
    }
}
