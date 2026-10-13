<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 첨부 문서 앨범 인덱스 — 카드의 openAlbum 인덱스가 앨범 배열(최신순 재인덱스)과
 * 일치해야 클릭한 이미지가 열린다 (조유신 피드백: 1번 클릭 시 6번이 열리던 역순 버그).
 */
class ProjectDocumentAlbumIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_doc_card_index_matches_album_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);
        $project = Project::create(['client_id' => $client->id, 'name' => '앨범 인덱스', 'stage' => 'consulting']);

        $old = ProjectDocument::create(['project_id' => $project->id, 'file_name' => 'old-photo.png', 'file_path' => 'x/old.png', 'mime_type' => 'image/png', 'file_size' => 10]);
        $old->forceFill(['created_at' => now()->subDay()])->save();
        ProjectDocument::create(['project_id' => $project->id, 'file_name' => 'new-photo.png', 'file_path' => 'x/new.png', 'mime_type' => 'image/png', 'file_size' => 10]);

        $html = $this->actingAs($admin)->get("/projects/{$project->id}")->assertOk()->getContent();

        // 그리드 첫 카드 = 최신(new-photo) = openAlbum(0), 두 번째 = old = openAlbum(1)
        $this->assertSame(1, preg_match('/onclick="openAlbum\((\d+)\)"[^>]*>(?:(?!doc-thumb-card).)*?new-photo\.png/s', $html, $m), '최신 문서 카드를 찾지 못함');
        $this->assertSame('0', $m[1], '최신 문서 카드의 앨범 인덱스가 0이 아님 (역순 버그)');
        $this->assertSame(1, preg_match('/onclick="openAlbum\((\d+)\)"[^>]*>(?:(?!doc-thumb-card).)*?old-photo\.png/s', $html, $m2));
        $this->assertSame('1', $m2[1]);
    }

    public function test_client_doc_card_index_matches_album_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '고블린', 'grade' => 'normal']);

        $old = ClientDocument::create(['client_id' => $client->id, 'file_name' => 'old-c.png', 'file_path' => 'x/oc.png', 'mime_type' => 'image/png', 'file_size' => 10]);
        $old->forceFill(['created_at' => now()->subDay()])->save();
        ClientDocument::create(['client_id' => $client->id, 'file_name' => 'new-c.png', 'file_path' => 'x/nc.png', 'mime_type' => 'image/png', 'file_size' => 10]);

        $html = $this->actingAs($admin)->get("/clients/{$client->id}")->assertOk()->getContent();

        $this->assertSame(1, preg_match('/onclick="openAlbum\((\d+)\)"[^>]*>(?:(?!doc-thumb-card).)*?new-c\.png/s', $html, $m), '최신 문서 카드를 찾지 못함');
        $this->assertSame('0', $m[1], '최신 문서 카드의 앨범 인덱스가 0이 아님 (역순 버그)');
    }
}
