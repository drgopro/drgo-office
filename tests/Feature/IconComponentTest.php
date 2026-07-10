<?php

namespace Tests\Feature;

use Tests\TestCase;

class IconComponentTest extends TestCase
{
    /**
     * 아이콘 컴포넌트는 JS 문자열 리터럴 안에서도 사용되므로
     * 출력에 줄바꿈이 섞이면 인라인 스크립트가 SyntaxError로 죽는다.
     */
    public function test_icon_renders_as_single_line(): void
    {
        $rendered = trim($this->blade('<x-icon name="clip" :size="30"/>'));

        $this->assertStringStartsWith('<svg', $rendered);
        $this->assertStringEndsWith('</svg>', $rendered);
        $this->assertStringNotContainsString("\n", $rendered);
    }

    public function test_unknown_icon_renders_nothing(): void
    {
        $rendered = trim($this->blade('<x-icon name="no-such-icon"/>'));

        $this->assertSame('', $rendered);
    }
}
