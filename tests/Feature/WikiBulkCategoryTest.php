<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wiki;
use App\Models\WikiCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WikiBulkCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeWiki(?int $categoryId = null, string $title = '문서'): Wiki
    {
        return Wiki::create([
            'title' => $title,
            'category' => '일반',
            'category_id' => $categoryId,
            'content' => '<p>내용</p>',
        ]);
    }

    public function test_bulk_move_updates_category_and_name(): void
    {
        $user = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $from = WikiCategory::create(['name' => '기존', 'sort_order' => 1]);
        $to = WikiCategory::create(['name' => '이동대상', 'sort_order' => 2]);
        $a = $this->makeWiki($from->id, '문서A');
        $b = $this->makeWiki($from->id, '문서B');
        $untouched = $this->makeWiki($from->id, '문서C');

        $response = $this->actingAs($user)->postJson('/api/wiki/bulk-category', [
            'ids' => [$a->id, $b->id],
            'category_id' => $to->id,
        ]);

        $response->assertOk()->assertJson(['ok' => true, 'moved' => 2]);
        $this->assertSame($to->id, $a->fresh()->category_id);
        $this->assertSame('이동대상', $a->fresh()->category);
        $this->assertSame($to->id, $b->fresh()->category_id);
        $this->assertSame($from->id, $untouched->fresh()->category_id);
    }

    public function test_bulk_move_to_uncategorized(): void
    {
        $user = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $from = WikiCategory::create(['name' => '기존', 'sort_order' => 1]);
        $a = $this->makeWiki($from->id);

        $response = $this->actingAs($user)->postJson('/api/wiki/bulk-category', [
            'ids' => [$a->id],
            'category_id' => null,
        ]);

        $response->assertOk();
        $this->assertNull($a->fresh()->category_id);
        $this->assertSame('미분류', $a->fresh()->category);
    }

    public function test_bulk_move_rejects_invalid_category(): void
    {
        $user = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $a = $this->makeWiki();

        $response = $this->actingAs($user)->postJson('/api/wiki/bulk-category', [
            'ids' => [$a->id],
            'category_id' => 99999,
        ]);

        $response->assertUnprocessable();
    }

    public function test_bulk_move_requires_ids(): void
    {
        $user = User::factory()->create(['role' => 'member', 'is_active' => true]);

        $response = $this->actingAs($user)->postJson('/api/wiki/bulk-category', [
            'ids' => [],
            'category_id' => null,
        ]);

        $response->assertUnprocessable();
    }
}
