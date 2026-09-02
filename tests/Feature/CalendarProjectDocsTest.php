<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 프로젝트 첨부 문서 캘린더 연동 — 의뢰자 상세 API가 프로젝트별 문서를 제공하고 캘린더가 읽기 전용으로 표시 */
class CalendarProjectDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_detail_exposes_per_project_documents(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['name' => '테스트 의뢰자', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '집 세팅']);
        $empty = Project::create(['client_id' => $client->id, 'name' => '문서 없는 프로젝트']);
        $img = $project->documents()->create([
            'file_name' => '방사진.jpg', 'file_path' => 'projects/1/room.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 1234, 'note' => '사진/이미지 - 안방',
        ]);
        $pdf = $project->documents()->create([
            'file_name' => '계약서.pdf', 'file_path' => 'projects/1/contract.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 5678, 'note' => '계약서',
        ]);

        $res = $this->actingAs($admin)->getJson("/api/clients/{$client->id}/detail")->assertOk()->json();
        $byId = collect($res['projects'])->keyBy('id');

        $docs = collect($byId[$project->id]['documents']);
        $this->assertCount(2, $docs);
        $imgDoc = $docs->firstWhere('id', $img->id);
        $this->assertSame('방사진.jpg', $imgDoc['file_name']);
        $this->assertStringContainsString("/project-documents/{$img->id}/thumb", $imgDoc['thumb_url']);
        $this->assertStringContainsString("/project-documents/{$img->id}/view", $imgDoc['view_url']);
        $pdfDoc = $docs->firstWhere('id', $pdf->id);
        $this->assertNull($pdfDoc['thumb_url']); // 비이미지는 썸네일 없음
        $this->assertSame([], $byId[$empty->id]['documents']);
    }

    public function test_calendar_renders_linked_project_docs_ui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/calendar')->assertOk()
            ->assertSee('linkedProjDocsWrap', false)   // 수정 뷰 컨테이너
            ->assertSee('renderLinkedProjDocs', false) // 렌더 함수 + 프로젝트 변경 훅
            ->assertSee('프로젝트 첨부 문서', false);      // 요약 뷰 섹션 라벨
    }
}
