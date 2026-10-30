<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientFieldDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** 의뢰자 상세 '사업자 정보' — 사업자등록증 파일 첨부·미리보기 (커스텀 필드가 없어도 첨부 칸 표시) */
class ClientBusinessDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_view_renders_business_section_with_certificate_upload_and_preview(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($user)->get('/clients')->assertOk()->getContent();

        // 사업자 정보 섹션은 커스텀 필드와 무관하게 항상 렌더되고, 사업자등록증 첨부 칸 + 바로 업로드 + 앨범 뷰어 미리보기
        $this->assertStringContainsString("cvSection('사업자 정보', businessRail, business)", $html);
        $this->assertStringContainsString('function cvBizDocsHtml', $html);
        $this->assertStringContainsString('async function bizDocUpload', $html);
        $this->assertStringContainsString('accept="image/*,application/pdf"', $html);
        $this->assertStringContainsString("const BIZ_DOC_CATEGORY = '사업자등록증'", $html);
        // 사업자 섹션 커스텀 필드(현금영수증 번호 등)는 기타 섹션 루프에서 빠져 중복 표시되지 않음
        $this->assertStringContainsString("!['equipment', 'business'].includes(f.section || 'etc')", $html);
    }

    public function test_uploading_certificate_records_category_in_note_so_view_can_filter_it(): void
    {
        Storage::fake();
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::create(['nickname' => '도현게임즈', 'grade' => 'normal', 'status' => 'active']);
        ClientFieldDefinition::create(['key' => 'cash_receipt_no', 'label' => '현금영수증 번호', 'type' => 'text', 'section' => 'business']);

        $this->actingAs($user)->postJson("/clients/{$client->id}/documents", [
            'files' => [UploadedFile::fake()->create('사업자등록증.pdf', 120, 'application/pdf')],
            'category' => '사업자등록증',
        ])->assertOk()->assertJsonPath('count', 1);

        $this->assertDatabaseHas('client_documents', ['client_id' => $client->id, 'note' => '사업자등록증', 'mime_type' => 'application/pdf']);

        $doc = $this->actingAs($user)->getJson("/api/clients/{$client->id}/detail")->assertOk()->json('documents.0');
        $this->assertSame('사업자등록증', $doc['note']);
        $this->assertStringContainsString("/documents/{$doc['id']}/view", $doc['view_url'], 'PDF 미리보기(iframe)용 URL');
        $this->assertNull($doc['thumb_url'], 'PDF 는 썸네일 없이 PDF 배지로 표시');
    }
}
