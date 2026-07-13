<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WikiTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WikiTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['role' => 'member']);
    }

    public function test_member_creates_template(): void
    {
        $res = $this->actingAs($this->member())->postJson('/api/wiki-templates', [
            'name' => '  회의록 서식  ',
            'content' => '<h1>주간 회의</h1><p>참석자: </p><p>안건: </p>',
        ]);

        $res->assertCreated()->assertJsonPath('name', '회의록 서식');
        $this->assertSame('회의록 서식', WikiTemplate::first()->name, '이름 앞뒤 공백 제거');
    }

    public function test_template_name_and_content_are_required(): void
    {
        $this->actingAs($this->member())->postJson('/api/wiki-templates', [
            'name' => '', 'content' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'content']);
    }

    public function test_index_lists_without_content_and_show_returns_content(): void
    {
        $creator = $this->member();
        $tpl = WikiTemplate::factory()->create([
            'name' => '업무 보고 서식',
            'content' => '<h2>보고</h2>',
            'created_by' => $creator->id,
        ]);

        $list = $this->actingAs($this->member())->getJson('/api/wiki-templates');
        $list->assertOk()
            ->assertJsonPath('0.name', '업무 보고 서식')
            ->assertJsonPath('0.creator', $creator->display_name)
            ->assertJsonMissingPath('0.content');

        $this->actingAs($this->member())->getJson("/api/wiki-templates/{$tpl->id}")
            ->assertOk()
            ->assertJsonPath('content', '<h2>보고</h2>');
    }

    public function test_member_updates_and_deletes_template(): void
    {
        $tpl = WikiTemplate::factory()->create(['name' => '옛 이름']);

        $this->actingAs($this->member())->patchJson("/api/wiki-templates/{$tpl->id}", [
            'name' => '새 이름',
        ])->assertOk()->assertJsonPath('name', '새 이름');
        $this->assertSame('새 이름', $tpl->fresh()->name);

        $this->actingAs($this->member())->deleteJson("/api/wiki-templates/{$tpl->id}")->assertOk();
        $this->assertNull(WikiTemplate::find($tpl->id));
    }

    public function test_guest_cannot_access_templates(): void
    {
        $this->getJson('/api/wiki-templates')->assertUnauthorized();
    }
}
