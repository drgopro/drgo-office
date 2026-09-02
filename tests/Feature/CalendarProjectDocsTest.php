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
            'mime_type' => 'image/jpeg', 'file_size' => 1234, 'note' => '방 사진 - 안방',
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
        $this->assertSame('방 사진', $imgDoc['category']); // note 앞부분에서 분류 추출
        $this->assertSame('안방', $imgDoc['note']);        // 분류를 뗀 메모만
        $this->assertStringContainsString("/project-documents/{$img->id}/thumb", $imgDoc['thumb_url']);
        $this->assertStringContainsString("/project-documents/{$img->id}/view", $imgDoc['view_url']);
        $pdfDoc = $docs->firstWhere('id', $pdf->id);
        $this->assertNull($pdfDoc['thumb_url']); // 비이미지는 썸네일 없음
        $this->assertSame('계약서', $pdfDoc['category']);
        $this->assertSame([], $byId[$empty->id]['documents']);
    }

    public function test_document_category_parsing_edge_cases(): void
    {
        $client = Client::create(['name' => '테스트 의뢰자', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '집 세팅']);
        $mk = fn (string $note) => $project->documents()->create([
            'file_name' => 'f.bin', 'file_path' => 'projects/1/f.bin',
            'mime_type' => 'application/octet-stream', 'file_size' => 1, 'note' => $note,
        ]);

        $this->assertSame('레퍼런스', $mk('레퍼런스')->category());
        $this->assertSame('방문 보고서', $mk('방문 보고서 · 이미지')->category()); // 보고서 인라인 업로드
        $this->assertSame('기타', $mk('자유 메모만 적은 경우')->category());       // 목록 밖 값
        $this->assertSame('', $mk('자유 메모만 적은 경우')->noteBody());
    }

    public function test_project_page_shows_category_options_and_filter_tabs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['name' => '테스트 의뢰자', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '집 세팅']);
        $project->documents()->create([
            'file_name' => 'a.jpg', 'file_path' => 'p/a.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 1, 'note' => '방 사진',
        ]);
        $project->documents()->create([
            'file_name' => 'b.jpg', 'file_path' => 'p/b.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 1, 'note' => '레퍼런스 - 조명 참고',
        ]);

        $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()
            ->assertSee('<option value="방 사진">방 사진</option>', false)   // 업로드 분류 추가
            ->assertSee('<option value="레퍼런스">레퍼런스</option>', false)
            ->assertSee('docCatTabs', false)                               // 분류 필터 탭
            ->assertSee('방 사진 1', false)
            ->assertSee('레퍼런스 1', false)
            ->assertSee('data-doc-cat="방 사진"', false);
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
