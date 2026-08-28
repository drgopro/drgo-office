<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 견적서 인쇄 — PNG 저장 파일명 'yyyy-mm-dd 닉네임(이름).png' */
class EstimatePrintFilenameTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    private function makeEstimate(array $attrs = []): Estimate
    {
        return Estimate::create(array_merge([
            'status' => 'created', 'product_items' => [], 'service_items' => [],
            'product_total' => 0, 'total_amount' => 0, 'created_by' => $this->user->id,
        ], $attrs));
    }

    public function test_png_filename_uses_nickname_and_name(): void
    {
        $estimate = $this->makeEstimate(['client_nickname' => '고블린', 'client_name' => '홍길동']);

        $this->actingAs($this->user)->get("/estimates/{$estimate->id}/print")
            ->assertOk()
            ->assertSee('link.download=`${ds} ${'.json_encode('고블린(홍길동)').'}.png`', false);
    }

    public function test_list_export_uses_same_filename_format(): void
    {
        // 목록의 출력(이미지/PDF)도 'yyyy-mm-dd 닉네임(이름)' 형식 — estExportName 공용 사용
        $this->actingAs($this->user)->get('/estimates')
            ->assertOk()
            ->assertSee('link.download = `${estExportName(id)}.png`', false)
            ->assertSee('pdf.save(`${estExportName(id)}.pdf`)', false)
            ->assertSee('window.__estMeta[e.id]', false);
    }

    public function test_png_filename_falls_back_to_estimate_number(): void
    {
        $estimate = $this->makeEstimate();

        $this->actingAs($this->user)->get("/estimates/{$estimate->id}/print")
            ->assertOk()
            ->assertSee(json_encode('견적서#'.$estimate->display_no), false);
    }
}
