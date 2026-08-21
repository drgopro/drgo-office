<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 익명(의뢰자 미연동) 프로젝트의 상담 이력 등록 — client_id 없이도 저장돼야 함 */
class AnonymousProjectConsultationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'consulted_at' => '2026-08-20',
            'consult_type' => 'phone',
            'result' => 'in_progress',
            'content' => '전화 상담 내용',
        ];
    }

    public function test_consultation_saves_on_anonymous_project(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $project = Project::create([
            'client_id' => null, 'manual_client_name' => '익명 문의자',
            'name' => '익명 프로젝트', 'project_type' => 'visit', 'stage' => 'consulting',
        ]);

        $this->actingAs($user)
            ->post("/projects/{$project->id}/consultations", $this->payload())
            ->assertRedirect();

        $c = Consultation::where('project_id', $project->id)->first();
        $this->assertNotNull($c);
        $this->assertNull($c->client_id);
        $this->assertSame('전화 상담 내용', $c->content);
    }

    public function test_consultation_still_updates_last_contact_for_linked_client(): void
    {
        $user = User::factory()->create(['role' => 'master']);
        $client = Client::create(['nickname' => '연동 의뢰자', 'grade' => 'normal', 'last_contact_at' => null]);
        $project = Project::create([
            'client_id' => $client->id, 'name' => '연동 프로젝트',
            'project_type' => 'visit', 'stage' => 'consulting',
        ]);

        $this->actingAs($user)
            ->post("/projects/{$project->id}/consultations", $this->payload())
            ->assertRedirect();

        $this->assertSame($client->id, Consultation::where('project_id', $project->id)->value('client_id'));
        $this->assertNotNull($client->fresh()->last_contact_at);
    }
}
